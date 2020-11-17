<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Processor\Doctrine\DBAL;

use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Types;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\OrderExpression;
use Solido\QueryLanguage\Processor\Doctrine\FieldInterface;
use Solido\QueryLanguage\Walker\DBAL\SqlWalker;

use function is_string;

/**
 * @internal
 */
class Field implements FieldInterface
{
    private string $fieldType;
    public string $fieldName;
    public ?string $tableName;

    /** @var string|callable|null */
    public $validationWalker;

    /** @var string|callable|null */
    public $customWalker;

    public function __construct(string $fieldName, ?string $tableName = null, string $type = Types::STRING)
    {
        $this->fieldName = $fieldName;
        $this->tableName = $tableName;
        $this->fieldType = $type;

        $this->validationWalker = null;
        $this->customWalker = null;
    }

    /**
     * Adds condition to query builder.
     *
     * @param QueryBuilder $queryBuilder
     */
    public function addCondition(object $queryBuilder, ExpressionInterface $expression): void
    {
        $this->addWhereCondition($queryBuilder, $expression);
    }

    /**
     * Adds a simple condition to the query builder.
     */
    private function addWhereCondition(QueryBuilder $queryBuilder, ExpressionInterface $expression): void
    {
        $walker = $this->customWalker;
        $fieldName = ($this->tableName ? $this->tableName . '.' : '') . $this->fieldName;

        if ($walker !== null) {
            $walker = is_string($walker) ? new $walker($queryBuilder, $fieldName) : $walker($queryBuilder, $fieldName, $this->fieldType);
        } else {
            $walker = new SqlWalker($queryBuilder, $fieldName, $this->fieldType);
        }

        $queryBuilder->andWhere($expression->dispatch($walker));
    }

    /**
     * {@inheritdoc}
     */
    public function getValidationWalker()
    {
        return $this->validationWalker;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrder(object $queryBuilder, OrderExpression $orderExpression): array
    {
        return [$this->fieldName, $orderExpression->getDirection()];
    }
}
