<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker\Doctrine;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use Solido\QueryLanguage\Expression\ValueExpression;

use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function Safe\json_encode;
use function sprintf;

class JsonWalker extends DqlWalker
{
    public const STRATEGY_EQUALS = 'equals';
    public const STRATEGY_CONTAINS = 'contains';

    public function __construct(
        QueryBuilder $queryBuilder,
        string $field,
        private readonly string $strategy = self::STRATEGY_EQUALS,
    ) {
        parent::__construct($queryBuilder, $field, Types::STRING);

        if ($this->strategy !== self::STRATEGY_EQUALS && $this->strategy !== self::STRATEGY_CONTAINS) {
            throw new InvalidArgumentException(sprintf(
                'Unknown json strategy "%s". Allowed: "%s", "%s".',
                $this->strategy,
                self::STRATEGY_EQUALS,
                self::STRATEGY_CONTAINS,
            ));
        }
    }

    public function walkComparison(string $operator, ValueExpression $expression): mixed
    {
        if ($operator !== '=') {
            return parent::walkComparison($operator, $expression);
        }

        if ($expression->getValue() === null) {
            return new Expr\Comparison($this->field, 'IS', 'NULL');
        }

        $parameterName = $this->generateParameterName();
        $this->queryBuilder->setParameter(
            $parameterName,
            $this->formatParameter($expression->dispatch($this)),
            Types::STRING,
        );

        return new Expr\Comparison(
            $this->stringifyField($this->field),
            $this->strategy === self::STRATEGY_CONTAINS ? 'LIKE' : Expr\Comparison::EQ,
            ':' . $parameterName,
        );
    }

    private function stringifyField(string $field): string
    {
        return "CONCAT('', " . $field . ')';
    }

    private function normalizeToString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return json_encode($value);
    }

    private function formatParameter(mixed $value): string
    {
        $value = $this->normalizeToString($value);

        if ($this->strategy === self::STRATEGY_CONTAINS) {
            return '%' . $value . '%';
        }

        return $value;
    }
}
