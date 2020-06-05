<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Processor\Doctrine\ORM;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Solido\QueryLanguage\Exception\Doctrine\FieldNotFoundException;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\OrderExpression;
use Solido\QueryLanguage\Processor\Doctrine\FieldInterface;
use Solido\QueryLanguage\Walker\Doctrine\DiscriminatorWalker;
use Solido\QueryLanguage\Walker\Doctrine\DqlWalker;
use Solido\QueryLanguage\Walker\Validation\EnumWalker;
use function array_keys;
use function count;
use function is_string;

/**
 * @internal
 */
class Field implements FieldInterface
{
    private string $rootAlias;
    private string $fieldType;
    private EntityManagerInterface $entityManager;
    public string $fieldName;

    /**
     * @var array<string, mixed>
     * @phpstan-var array<array{fieldName: string, targetEntity: string}>
     */
    public array $associations;

    /** @var array<string, mixed> */
    public array $mapping;

    /** @var string|callable|null */
    public $validationWalker;

    /** @var string|callable|null */
    public $customWalker;

    public bool $discriminator;

    public function __construct(
        string $fieldName,
        string $rootAlias,
        ClassMetadata $rootEntity,
        EntityManagerInterface $entityManager
    ) {
        $this->fieldName = $fieldName;
        $this->rootAlias = $rootAlias;
        $this->entityManager = $entityManager;
        $this->validationWalker = null;
        $this->customWalker = null;
        $this->discriminator = false;

        [$rootField, $rest] = MappingHelper::processFieldName($rootEntity, $fieldName);

        if ($rootField === null) {
            $this->searchForDiscriminator($rootEntity, $fieldName);
        }

        $this->mapping = $rootField;
        $this->fieldType = isset($this->mapping['targetEntity']) ? 'string' : ($this->mapping['type'] ?? 'string');
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
        if (! $this->isAssociation()) {
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
        $alias = $this->getMappingFieldName();
        $walker = $this->customWalker;

        $subQb = $this->entityManager->createQueryBuilder()
            ->select('1')
            ->from($this->getTargetEntity(), $alias)
            ->setParameters($queryBuilder->getParameters());

        $currentFieldName = $alias;
        $currentAlias = $alias;
        foreach ($this->associations as $association) {
            if (isset($association['targetEntity'])) {
                $subQb->join($currentFieldName . '.' . $association['fieldName'], $association['fieldName']);
                $currentAlias = $association['fieldName'];
                $currentFieldName = $association['fieldName'];
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

        if ($this->isManyToMany()) {
            $queryBuilder
                ->distinct()
                ->join($this->rootAlias . '.' . $alias, $alias, Join::WITH, $subQb->getDQLPart('where'));
        } else {
            if ($this->isOwningSide()) {
                $subQb->andWhere($subQb->expr()->eq($this->rootAlias . '.' . $alias, $alias));
            } else {
                $subQb->andWhere($subQb->expr()->eq($alias . '.' . $this->mapping['inversedBy'], $this->rootAlias));
            }

            $queryBuilder
                ->andWhere($queryBuilder->expr()->exists($subQb->getDQL()))
                ->setParameters($subQb->getParameters());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getValidationWalker()
    {
        return $this->validationWalker;
    }

    /**
     * Gets the mapping field name.
     */
    public function getMappingFieldName(): string
    {
        return $this->mapping['fieldName'];
    }

    /**
     * Whether this field navigates into associations.
     */
    public function isAssociation(): bool
    {
        return isset($this->mapping['targetEntity']) || 0 < count($this->associations);
    }

    /**
     * Gets the association target entity.
     */
    public function getTargetEntity(): string
    {
        return $this->mapping['targetEntity'];
    }

    /**
     * Whether this association is a many to many.
     */
    public function isManyToMany(): bool
    {
        return isset($this->mapping['joinTable']);
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
     * {@inheritdoc}
     */
    public function getOrder(object $queryBuilder, OrderExpression $orderExpression): array
    {
        return [ $this->fieldName, $orderExpression->getDirection() ];
    }
}
