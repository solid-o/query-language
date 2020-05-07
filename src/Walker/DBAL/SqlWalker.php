<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker\DBAL;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Types;
use Safe\DateTime;
use Safe\DateTimeImmutable;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\Literal\LiteralExpression;
use Solido\QueryLanguage\Expression\Literal\NullExpression;
use Solido\QueryLanguage\Expression\Literal\StringExpression;
use Solido\QueryLanguage\Expression\ValueExpression;
use Solido\QueryLanguage\Walker\AbstractWalker;
use function array_key_exists;
use function array_map;
use function implode;
use function mb_strtolower;
use function Safe\preg_replace;

class SqlWalker extends AbstractWalker
{
    private AbstractPlatform $platform;
    protected QueryBuilder $queryBuilder;
    private ?string $columnType;
    private string $fieldName;

    public function __construct(QueryBuilder $queryBuilder, string $field, ?string $columnType = null)
    {
        $this->platform = $queryBuilder->getConnection()->getDatabasePlatform();
        parent::__construct($this->platform->quoteIdentifier($field));

        $this->fieldName = $field;
        $this->queryBuilder = $queryBuilder;
        $this->columnType = $columnType;
    }

    /**
     * {@inheritdoc}
     */
    public function walkLiteral(LiteralExpression $expression)
    {
        $value = parent::walkLiteral($expression);
        if ($expression instanceof NullExpression) {
            return $value;
        }

        switch ($this->columnType) {
            case Types::DATETIME_MUTABLE:
            case Types::DATETIMETZ_MUTABLE:
                return new DateTime($value);
            case Types::DATETIME_IMMUTABLE:
            case Types::DATETIMETZ_IMMUTABLE:
                return new DateTimeImmutable($value);
            default:
                return $value;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function walkComparison(string $operator, ValueExpression $expression)
    {
        $field = $this->field;
        if ($expression instanceof NullExpression) {
            return $field . ' IS NULL';
        }

        if ($operator === 'like') {
            $field = $this->platform->getLowerExpression($this->field);
            $expression = StringExpression::create('%' . mb_strtolower((string) $expression) . '%');
        }

        $parameterName = $this->generateParameterName();
        $this->queryBuilder->setParameter($parameterName, $expression->dispatch($this), $this->columnType);

        return $field . ' ' . $operator . ' :' . $parameterName;
    }

    /**
     * {@inheritdoc}
     */
    public function walkAll()
    {
        // Do nothing.
    }

    /**
     * {@inheritdoc}
     */
    public function walkOrder(string $field, string $direction)
    {
        return 'ORDER BY ' . $this->platform->quoteIdentifier($field) . ' ' . $direction;
    }

    /**
     * {@inheritdoc}
     */
    public function walkNot(ExpressionInterface $expression)
    {
        return $this->platform->getNotExpression($expression->dispatch($this));
    }

    /**
     * {@inheritdoc}
     */
    public function walkAnd(array $arguments)
    {
        return '(' . implode(' AND ', array_map(
            fn (ExpressionInterface $expression) => $expression->dispatch($this),
            $arguments
        )) . ')';
    }

    /**
     * {@inheritdoc}
     */
    public function walkOr(array $arguments)
    {
        return '(' . implode(' OR ', array_map(
            fn (ExpressionInterface $expression) => $expression->dispatch($this),
            $arguments
        )) . ')';
    }

    /**
     * {@inheritdoc}
     */
    public function walkEntry(string $key, ExpressionInterface $expression)
    {
        $walker = new SqlWalker($this->queryBuilder, $this->fieldName . '.' . $key);

        return $expression->dispatch($walker);
    }

    /**
     * Generates a unique parameter name for current field.
     */
    protected function generateParameterName(): string
    {
        $params = $this->queryBuilder->getParameters();
        $underscoreField = mb_strtolower(
            preg_replace('/(?|(?<=[a-z0-9])([A-Z])|(?<=[A-Z]{2})([a-z]))/', '_$1', $this->fieldName)
        );
        $parameterName = $origParamName = preg_replace('/\W+/', '_', $underscoreField);

        $i = 1;
        while (array_key_exists($parameterName, $params)) {
            $parameterName = $origParamName . '_' . $i++;
        }

        return $parameterName;
    }
}
