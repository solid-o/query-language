<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Processor\Doctrine\PhpCr;

use Doctrine\ODM\PHPCR\DocumentManagerInterface;
use Doctrine\ODM\PHPCR\Mapping\ClassMetadata;
use Doctrine\ODM\PHPCR\Query\Builder\AbstractNode;
use Doctrine\ODM\PHPCR\Query\Builder\From;
use Doctrine\ODM\PHPCR\Query\Builder\QueryBuilder;
use Doctrine\ODM\PHPCR\Query\Builder\WhereAnd;
use RuntimeException;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\OrderExpression;
use Solido\QueryLanguage\Processor\Doctrine\FieldInterface;
use Solido\QueryLanguage\Walker\PhpCr\NodeWalker;
use function assert;
use function count;
use function is_string;

/**
 * @internal
 */
class Field implements FieldInterface
{
    private string $rootAlias;
    private string $fieldType;

    /**
     * @var mixed[]
     * @phpstan-var array<array{id?: bool, fieldName: string, type: string, targetDocument?: string, sourceDocument?: string}>
     */
    private array $associations;

    public string $fieldName;

    /**
     * @var array<string, string|bool>
     * @phpstan-var array{id?: bool, fieldName: string, type?: string, targetDocument?: string, sourceDocument?: string}
     */
    private array $mapping;

    /** @var string|callable|null */
    public $validationWalker;

    /** @var string|callable|null */
    public $customWalker;

    private DocumentManagerInterface $documentManager;

    public function __construct(
        string $fieldName,
        string $rootAlias,
        ClassMetadata $rootEntity,
        DocumentManagerInterface $documentManager
    ) {
        $this->fieldName = $fieldName;
        $this->rootAlias = $rootAlias;
        $this->documentManager = $documentManager;
        $this->validationWalker = null;
        $this->customWalker = null;

        [$rootField, $rest] = MappingHelper::processFieldName($rootEntity, $fieldName);
        $this->mapping = $rootField;

        $this->fieldType = 'string';
        // @phpstan-ignore-next-line
        if (isset($this->mapping['type']) && ! isset($this->mapping['targetDocument'])) {
            $this->fieldType = $this->mapping['type'];
        }

        $this->associations = [];
        if ($rest === null) {
            return;
        }

        $this->processAssociations($documentManager, $rest);
    }

    /**
     * Adds condition to query builder.
     *
     * @param QueryBuilder $queryBuilder
     */
    public function addCondition(object $queryBuilder, ExpressionInterface $expression): void
    {
        assert($queryBuilder instanceof QueryBuilder);

        if ($this->isAssociation()) {
            $this->addAssociationCondition($queryBuilder, $expression);
        } else {
            $this->addWhereCondition($queryBuilder, $expression);
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
     * Whether this column navigates into associations.
     */
    public function isAssociation(): bool
    {
        return isset($this->mapping['targetDocument']) || 0 < count($this->associations);
    }

    /**
     * Processes an association column and attaches the conditions to the query builder.
     */
    private function addAssociationCondition(QueryBuilder $queryBuilder, ExpressionInterface $expression): void
    {
        $alias = $this->getMappingFieldName();
        $walker = $this->customWalker;

        $targetDocument = $this->documentManager->getClassMetadata($this->getTargetDocument());
        assert($targetDocument instanceof ClassMetadata);

        if ($targetDocument->uuidFieldName === null) {
            throw new RuntimeException('Uuid field must be declared to build association conditions');
        }

        // @phpstan-ignore-next-line
        $queryBuilder->addJoinInner()
            ->right()->document($this->getTargetDocument(), $alias)->end()
            ->condition()->equi($this->rootAlias . '.' . $alias, $alias . '.' . $targetDocument->uuidFieldName)->end()
        ->end();

        $currentFieldName = $alias;
        $currentAlias = $alias;
        foreach ($this->associations as $association) {
            if (isset($association['targetDocument'])) {
                $from = $queryBuilder->getChildOfType(AbstractNode::NT_FROM);
                assert($from instanceof From);

                // @phpstan-ignore-next-line
                $from->joinInner()
                    ->left()->document($association['sourceDocument'], $currentAlias)->end()
                    ->right()->document($association['targetDocument'], $currentFieldName = $association['fieldName'])->end()
                ->end();

                $currentAlias = $association['fieldName'];
            } else {
                $currentFieldName = $currentAlias . '.' . $association['fieldName'];
            }
        }

        if ($walker !== null) {
            $walker = is_string($walker) ? new $walker($queryBuilder, $currentFieldName) : $walker($queryBuilder, $currentFieldName, $this->fieldType);
        } else {
            $walker = new NodeWalker($currentFieldName, $this->fieldType);
        }

        $where = new WhereAnd();
        $where->addChild($expression->dispatch($walker));

        $queryBuilder->addChild($where);
    }

    /**
     * Adds a simple condition to the query builder.
     */
    private function addWhereCondition(QueryBuilder $queryBuilder, ExpressionInterface $expression): void
    {
        $alias = $this->getMappingFieldName();
        $walker = $this->customWalker;

        $fieldName = $this->rootAlias . '.' . $alias;
        if ($walker !== null) {
            $walker = is_string($walker) ? new $walker($fieldName) : $walker($fieldName, $this->fieldType);
        } else {
            $walker = new NodeWalker($fieldName, $this->fieldType);
        }

        $node = $expression->dispatch($walker);
        assert($node instanceof AbstractNode);
        if ($node->getNodeType() === AbstractNode::NT_CONSTRAINT) {
            $where = new WhereAnd();
            $where->addChild($node);

            $queryBuilder->addChild($where);
        } else {
            $queryBuilder->addChild($node);
        }
    }

    /**
     * Process associations chain.
     */
    private function processAssociations(DocumentManagerInterface $documentManager, string $rest): void
    {
        $associations = [];
        $associationField = $this->mapping;

        while ($rest !== null) {
            assert(isset($associationField['targetDocument']));

            $targetDocument = $documentManager->getClassMetadata($associationField['targetDocument']);
            assert($targetDocument instanceof ClassMetadata);

            [$associationField, $rest] = MappingHelper::processFieldName($targetDocument, $rest);
            assert(isset($associationField['type']));

            $associations[] = $associationField;
        }

        $this->associations = $associations;
    }

    /**
     * Gets the target document class.
     */
    private function getTargetDocument(): string
    {
        assert(isset($this->mapping['targetDocument']));

        return $this->mapping['targetDocument'];
    }

    /**
     * {@inheritdoc}
     */
    public function getOrder(object $queryBuilder, OrderExpression $orderExpression): array
    {
        return [ $this->fieldName, $orderExpression->getDirection() ];
    }
}
