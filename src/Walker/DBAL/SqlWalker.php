<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker\DBAL;

use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Types;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\Literal\LiteralExpression;
use Solido\QueryLanguage\Expression\Literal\NullExpression;
use Solido\QueryLanguage\Expression\Literal\StringExpression;
use Solido\QueryLanguage\Expression\ValueExpression;
use Solido\QueryLanguage\Walker\AbstractWalker;

use function array_key_exists;
use function array_map;
use function assert;
use function implode;
use function is_string;
use function mb_strtolower;
use function Safe\preg_replace;

class SqlWalker extends AbstractWalker
{
    private AbstractPlatform $platform;
    private string $quotedField;

    public function __construct(
        protected QueryBuilder $queryBuilder,
        private readonly string $field,
        private readonly string|null $fieldType = null,
    ) {
        $this->platform = $queryBuilder->getConnection()->getDatabasePlatform();
        $this->quotedField = $this->platform->quoteIdentifier($field);
    }

    public function walkLiteral(LiteralExpression $expression): mixed
    {
        $value = $expression->getValue();
        if ($expression instanceof NullExpression) {
            return $value;
        }

        switch ($this->fieldType) {
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

    public function walkComparison(string $operator, ValueExpression $expression): mixed
    {
        $field = $this->quotedField;
        if ($expression instanceof NullExpression) {
            return $field . ' IS NULL';
        }

        if ($operator === 'like') {
            $field = 'LOWER(' . $field . ')';
            $expression = StringExpression::create('%' . mb_strtolower((string) $expression) . '%');
        }

        $parameterName = $this->generateParameterName();
        $this->queryBuilder->setParameter($parameterName, $expression->dispatch($this), $this->fieldType ?? 'string');

        return $field . ' ' . $operator . ' :' . $parameterName;
    }

    public function walkAll(): mixed
    {
        return null;
    }

    public function walkOrder(string $field, string $direction): mixed
    {
        return 'ORDER BY ' . $this->platform->quoteIdentifier($field) . ' ' . $direction;
    }

    public function walkNot(ExpressionInterface $expression): mixed
    {
        return 'NOT(' . $expression->dispatch($this) . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function walkAnd(array $arguments): mixed
    {
        return '(' . implode(' AND ', array_map(
            fn (ExpressionInterface $expression) => $expression->dispatch($this),
            $arguments,
        )) . ')';
    }

    /**
     * {@inheritDoc}
     */
    public function walkOr(array $arguments): mixed
    {
        return '(' . implode(' OR ', array_map(
            fn (ExpressionInterface $expression) => $expression->dispatch($this),
            $arguments,
        )) . ')';
    }

    public function walkEntry(string $key, ExpressionInterface $expression): mixed
    {
        $walker = new SqlWalker($this->queryBuilder, $this->field . '.' . $key);

        return $expression->dispatch($walker);
    }

    /**
     * Generates a unique parameter name for current field.
     */
    protected function generateParameterName(): string
    {
        $params = $this->queryBuilder->getParameters();
        $underscoreField = mb_strtolower(preg_replace('/(?|(?<=[a-z0-9])([A-Z])|(?<=[A-Z]{2})([a-z]))/', '_$1', $this->field)); /* @phpstan-ignore-line */

        $parameterName = $origParamName = preg_replace('/\W+/', '_', $underscoreField);

        assert(is_string($parameterName));
        assert(is_string($origParamName));

        $i = 1;
        while (array_key_exists($parameterName, $params)) {
            $parameterName = $origParamName . '_' . $i++;
        }

        return $parameterName;
    }
}
