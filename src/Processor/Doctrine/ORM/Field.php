<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Processor\Doctrine\ORM;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\FieldMapping;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Solido\QueryLanguage\Exception\Doctrine\FieldNotFoundException;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\OrderExpression;
use Solido\QueryLanguage\Processor\Doctrine\FieldInterface;
use Solido\QueryLanguage\Walker\Doctrine\DiscriminatorWalker;
use Solido\QueryLanguage\Walker\Doctrine\DqlWalker;
use Solido\QueryLanguage\Walker\Validation\EnumWalker;
use Solido\QueryLanguage\Walker\Validation\ValidationWalkerInterface;

use function array_keys;
use function count;
use function is_string;

/** @internal */
class Field implements FieldInterface
{
    private string $fieldType;
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
    ) {
        $this->validationWalker = null;
        $this->customWalker = null;
        $this->discriminator = false;

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
        $walker = $this->customWalker;

        $fieldName = $this->discriminator ? $this->rootAlias : $this->rootAlias . '.' . $alias;
        if ($walker !== null) {
            $walker = is_string($walker) ? new $walker($queryBuilder, $fieldName) : $walker($queryBuilder, $fieldName, $this->fieldType);
        } else {
            $walker = new DqlWalker($queryBuilder, $fieldName, $this->fieldType);
        }

        $queryBuilder->andWhere($expression->dispatch($walker));
    }

    /**
     * Processes an association field and attaches the conditions to the query builder.
     */
    private function addAssociationCondition(QueryBuilder $queryBuilder, ExpressionInterface $expression): void
    {
        $alias = 'sub_' . $this->getMappingFieldName();
        $walker = $this->customWalker;

        $subQb = $this->entityManager->createQueryBuilder()
            ->select('1')
            ->from($this->getTargetEntity(), $alias)
            ->setParameters($queryBuilder->getParameters());

        $currentFieldName = $alias;
        $currentAlias = $alias;
        foreach ($this->associations as $association) {
            if ($association instanceof AssociationMapping) {
                $subQb->join($currentFieldName . '.' . $association->fieldName, 'sub_' . $association->fieldName);
                $currentAlias = $currentFieldName = 'sub_' . $association->fieldName;
            } elseif ($association instanceof FieldMapping) {
                $currentFieldName = $currentAlias . '.' . $association->fieldName;
            } elseif (isset($association['targetEntity'])) { /* @phpstan-ignore-line */
                $subQb->join($currentFieldName . '.' . $association['fieldName'], 'sub_' . $association['fieldName']);
                $currentAlias = $currentFieldName = 'sub_' . $association['fieldName'];
            } else {
                $currentFieldName = $currentAlias . '.' . $association['fieldName'];
            }
        }

        if ($walker !== null) {
            $walker = is_string($walker) ? new $walker($subQb, $currentFieldName) : $walker($subQb, $currentFieldName, $this->fieldType);
        } else {
            $walker = new DqlWalker($subQb, $currentFieldName, $this->fieldType);
        }

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
