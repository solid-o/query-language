<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Processor\Doctrine\ORM;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\FieldMapping;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use Solido\QueryLanguage\Exception\Doctrine\FieldNotFoundException;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\OrderExpression;
use Solido\QueryLanguage\Processor\Doctrine\FieldInterface;
use Solido\QueryLanguage\Walker\Doctrine\DiscriminatorWalker;
use Solido\QueryLanguage\Walker\Doctrine\DqlWalker;
use Solido\QueryLanguage\Walker\Doctrine\JsonWalker;
use Solido\QueryLanguage\Walker\TreeWalkerInterface;
use Solido\QueryLanguage\Walker\Validation\EnumWalker;
use Solido\QueryLanguage\Walker\Validation\ValidationWalkerInterface;

use function array_keys;
use function count;
use function is_string;
use function spl_object_id;
use function sprintf;

/** @internal */
class Field implements FieldInterface
{
    public const TO_MANY_STRATEGY_SPLIT = 'split';
    public const TO_MANY_STRATEGY_SHARED = 'shared';

    private string $fieldType;
    private string $aliasSuffix;
    private int $aliasIndex;
    private string $toManyStrategy;
    /**
     * @var array<string, mixed>
     * @phpstan-var array<AssociationMapping | FieldMapping | array{fieldName: string, targetEntity: string}>
     */
    public array $associations;

    /** @var array<string, mixed> */
    public FieldMapping|AssociationMapping|array $mapping;

    /** @var string|callable|null */
    public $validationWalker;

    /** @var string|callable|null */
    public $customWalker;

    public bool $discriminator;

    public function __construct(
        public string $fieldName,
        private readonly string $rootAlias,
        ClassMetadata $rootEntity,
        private readonly EntityManagerInterface $entityManager,
        string $toManyStrategy = self::TO_MANY_STRATEGY_SPLIT,
    ) {
        $this->validationWalker = null;
        $this->customWalker = null;
        $this->discriminator = false;
        $this->aliasSuffix = (string) spl_object_id($this);
        $this->aliasIndex = 0;
        $this->setToManyStrategy($toManyStrategy);

        [$rootField, $rest] = MappingHelper::processFieldName($rootEntity, $fieldName);

        if ($rootField === null) {
            $this->searchForDiscriminator($rootEntity, $fieldName);
        }

        $this->mapping = $rootField ?? [];
        if ($this->mapping instanceof AssociationMapping) {
            $this->fieldType = 'string';
        } elseif ($this->mapping instanceof FieldMapping) {
            $this->fieldType = $this->mapping->type;
        } else {
            $this->fieldType = isset($this->mapping['targetEntity']) ? 'string' : ($this->mapping['type'] ?? 'string');
        }

        $this->associations = [];

        if ($rest === null) {
            return;
        }

        $this->processAssociations($entityManager, $rest);
    }

    /**
     * Adds condition to query builder.
     *
     * @param QueryBuilder $queryBuilder
     */
    public function addCondition(object $queryBuilder, ExpressionInterface $expression): void
    {
        $assoc = $this->isAssociation();
        if ($assoc && ! $this->isToMany() && count($this->associations) === 0) {
            $analyzer = new AnalyzerWalker();
            $expression->dispatch($analyzer);
            $assoc = ! $analyzer->isSimple();
        }

        if (! $assoc) {
            $this->addWhereCondition($queryBuilder, $expression);
        } elseif ($this->shouldUseSharedJoinPath()) {
            $this->addSharedAssociationCondition($queryBuilder, $expression);
        } else {
            $this->addAssociationCondition($queryBuilder, $expression);
        }
    }

    /**
     * Adds a simple condition to the query builder.
     */
    private function addWhereCondition(QueryBuilder $queryBuilder, ExpressionInterface $expression): void
    {
        $alias = $this->discriminator ? null : $this->getMappingFieldName();
        $fieldName = $this->discriminator ? $this->rootAlias : $this->rootAlias . '.' . $alias;
        $walker = $this->createWalker($queryBuilder, $fieldName);

        $queryBuilder->andWhere($expression->dispatch($walker));
    }

    /**
     * Processes an association field and attaches the conditions to the query builder.
     */
    private function addAssociationCondition(QueryBuilder $queryBuilder, ExpressionInterface $expression): void
    {
        $alias = $this->buildAlias('sub_' . $this->getMappingFieldName());
        $walker = $this->customWalker;

        $subQb = $this->entityManager->createQueryBuilder()
            ->select('1')
            ->from($this->getTargetEntity(), $alias)
            ->setParameters($queryBuilder->getParameters());

        $currentFieldName = $alias;
        $currentAlias = $alias;
        $joinIndex = 0;
        foreach ($this->associations as $association) {
            if ($association instanceof AssociationMapping) {
                $associationAlias = $this->buildAlias('sub_' . $association->fieldName, $joinIndex++);
                $subQb->join($currentFieldName . '.' . $association->fieldName, $associationAlias);
                $currentAlias = $currentFieldName = $associationAlias;
            } elseif ($association instanceof FieldMapping) {
                $currentFieldName = $currentAlias . '.' . $association->fieldName;
            } elseif (isset($association['targetEntity'])) { /* @phpstan-ignore-line */
                $associationAlias = $this->buildAlias('sub_' . $association['fieldName'], $joinIndex++);
                $subQb->join($currentFieldName . '.' . $association['fieldName'], $associationAlias);
                $currentAlias = $currentFieldName = $associationAlias;
            } else {
                $currentFieldName = $currentAlias . '.' . $association['fieldName'];
            }
        }

        $walker = $this->createWalker($subQb, $currentFieldName);

        $subQb->where($expression->dispatch($walker));

        if ($this->isToMany()) {
            $queryBuilder
                ->distinct()
                ->join($this->rootAlias . '.' . $this->getMappingFieldName(), $alias, Join::WITH, $subQb->getDQLPart('where'));
        } else {
            if ($this->isOwningSide()) {
                $subQb->andWhere($subQb->expr()->eq($this->rootAlias . '.' . $this->getMappingFieldName(), $alias));
            } else {
                $subQb->andWhere($subQb->expr()->eq($alias . '.' . $this->mapping['mappedBy'], $this->rootAlias));
            }

            $queryBuilder
                ->andWhere($queryBuilder->expr()->exists($subQb->getDQL()))
                ->setParameters($subQb->getParameters());
        }
    }

    /**
     * Processes an association path directly on the root query, reusing existing joins.
     */
    private function addSharedAssociationCondition(QueryBuilder $queryBuilder, ExpressionInterface $expression): void
    {
        if (! $this->isToOnePath()) {
            $queryBuilder->distinct();
        }

        $currentAlias = $this->getOrCreateJoinAlias($queryBuilder, $this->rootAlias, $this->getMappingFieldName());
        $currentFieldName = $currentAlias;

        foreach ($this->associations as $association) {
            if ($association instanceof AssociationMapping) {
                $currentAlias = $this->getOrCreateJoinAlias($queryBuilder, $currentAlias, $association->fieldName);
                $currentFieldName = $currentAlias;
            } elseif ($association instanceof FieldMapping) {
                $currentFieldName = $currentAlias . '.' . $association->fieldName;
            } elseif (isset($association['targetEntity'])) { /* @phpstan-ignore-line */
                $currentAlias = $this->getOrCreateJoinAlias($queryBuilder, $currentAlias, $association['fieldName']);
                $currentFieldName = $currentAlias;
            } else {
                $currentFieldName = $currentAlias . '.' . $association['fieldName'];
            }
        }

        $walker = $this->createWalker($queryBuilder, $currentFieldName);

        $queryBuilder->andWhere($expression->dispatch($walker));
    }

    private function getOrCreateJoinAlias(QueryBuilder $queryBuilder, string $fromAlias, string $associationField): string
    {
        $joinPath = $fromAlias . '.' . $associationField;
        $joins = $queryBuilder->getDQLPart('join');

        if (isset($joins[$fromAlias])) {
            foreach ($joins[$fromAlias] as $join) {
                if ($join->getJoin() === $joinPath) {
                    return $join->getAlias();
                }
            }
        }

        $alias = $this->nextAlias('join_' . $associationField);
        $queryBuilder->join($joinPath, $alias);

        return $alias;
    }

    private function isToOnePath(): bool
    {
        if ($this->isToMany()) {
            return false;
        }

        foreach ($this->associations as $association) {
            if ($association instanceof AssociationMapping && ($association->type() & ClassMetadata::TO_MANY) !== 0) {
                return false;
            }

            if (isset($association['targetEntity']) && isset($association['type']) && ($association['type'] & ClassMetadata::TO_MANY) !== 0) {
                return false;
            }
        }

        return true;
    }

    private function shouldUseSharedJoinPath(): bool
    {
        return $this->isToOnePath() || $this->toManyStrategy === self::TO_MANY_STRATEGY_SHARED;
    }

    private function buildAlias(string $base, int|null $index = null): string
    {
        $alias = $base . '_' . $this->aliasSuffix;
        if ($index !== null) {
            $alias .= '_' . $index;
        }

        return $alias;
    }

    private function nextAlias(string $base): string
    {
        return $this->buildAlias($base, $this->aliasIndex++);
    }

    private function createWalker(QueryBuilder $queryBuilder, string $fieldName): TreeWalkerInterface
    {
        $walker = $this->customWalker;
        if ($walker !== null) {
            return is_string($walker) ? new $walker($queryBuilder, $fieldName) : $walker($queryBuilder, $fieldName, $this->fieldType);
        }

        if ($this->fieldType === Types::JSON) {
            return new JsonWalker($queryBuilder, $fieldName);
        }

        return new DqlWalker($queryBuilder, $fieldName, $this->fieldType);
    }

    public function setToManyStrategy(string $strategy): void
    {
        if ($strategy !== self::TO_MANY_STRATEGY_SPLIT && $strategy !== self::TO_MANY_STRATEGY_SHARED) {
            throw new InvalidArgumentException(sprintf(
                'Unknown to-many strategy "%s". Allowed: "%s", "%s".',
                $strategy,
                self::TO_MANY_STRATEGY_SPLIT,
                self::TO_MANY_STRATEGY_SHARED,
            ));
        }

        $this->toManyStrategy = $strategy;
    }

    public function getValidationWalker(): ValidationWalkerInterface|string|callable|null
    {
        return $this->validationWalker;
    }

    /**
     * Gets the mapping field name.
     */
    public function getMappingFieldName(): string
    {
        return $this->mapping instanceof FieldMapping || $this->mapping instanceof AssociationMapping ?
            $this->mapping->fieldName :
            $this->mapping['fieldName'];
    }

    /**
     * Whether this field navigates into associations.
     */
    public function isAssociation(): bool
    {
        return $this->mapping instanceof AssociationMapping || isset($this->mapping['targetEntity']) || 0 < count($this->associations);
    }

    /**
     * Gets the association target entity.
     *
     * @return class-string
     */
    public function getTargetEntity(): string
    {
        if ($this->mapping instanceof AssociationMapping) {
            return $this->mapping->targetEntity;
        }

        return $this->mapping['targetEntity'];
    }

    /**
     * Whether this association is a many to many.
     */
    public function isToMany(): bool
    {
        return isset($this->mapping['type']) && $this->mapping['type'] & ClassMetadata::TO_MANY;
    }

    /**
     * Whether this field represents the owning side of the association.
     */
    public function isOwningSide(): bool
    {
        return $this->mapping['isOwningSide'];
    }

    /**
     * Checks if the field name is a discriminator field name.
     */
    private function searchForDiscriminator(ClassMetadata $rootEntity, string $fieldName): void
    {
        if (! isset($rootEntity->discriminatorColumn['name']) || $fieldName !== $rootEntity->discriminatorColumn['name']) {
            throw new FieldNotFoundException($fieldName, $rootEntity->name);
        }

        $this->discriminator = true;
        $this->validationWalker = static function () use ($rootEntity): EnumWalker {
            /** @var string[] $keys */
            $keys = array_keys($rootEntity->discriminatorMap);

            return new EnumWalker($keys);
        };

        $this->customWalker = DiscriminatorWalker::class;
    }

    /**
     * Process associations chain.
     */
    private function processAssociations(EntityManagerInterface $entityManager, string $rest): void
    {
        $associations = [];
        $associationField = $this->mapping;

        while ($rest !== null) {
            $targetEntity = $entityManager->getClassMetadata($associationField['targetEntity']);
            [$associationField, $rest] = MappingHelper::processFieldName($targetEntity, $rest);

            if ($associationField === null) {
                throw new FieldNotFoundException($rest, $targetEntity->name);
            }

            $associations[] = $associationField;
        }

        $this->associations = $associations;
    }

    /**
     * {@inheritDoc}
     */
    public function getOrder(object $queryBuilder, OrderExpression $orderExpression): array
    {
        return [$this->fieldName, $orderExpression->getDirection()];
    }
}
