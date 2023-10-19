<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker\Doctrine;

use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\Literal\LiteralExpression;
use Solido\QueryLanguage\Expression\Literal\NullExpression;
use Solido\QueryLanguage\Expression\Literal\StringExpression;
use Solido\QueryLanguage\Expression\ValueExpression;
use Solido\QueryLanguage\Walker\AbstractWalker;

use function array_map;
use function assert;
use function is_string;
use function mb_strtolower;
use function Safe\preg_replace;

class DqlWalker extends AbstractWalker
{
    private const COMPARISON_MAP = [
        '=' => Expr\Comparison::EQ,
        '<' => Expr\Comparison::LT,
        '<=' => Expr\Comparison::LTE,
        '>' => Expr\Comparison::GT,
        '>=' => Expr\Comparison::GTE,
        'like' => 'LIKE',
    ];

    public function __construct(
        protected QueryBuilder $queryBuilder,
        protected string $field,
        private readonly string|null $fieldType = null,
    ) {
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
        $field = $this->field;
        if ($operator === 'like') {
            $field = 'LOWER(' . $field . ')';
            $expression = StringExpression::create('%' . mb_strtolower((string) $expression) . '%');
        }

        if ($expression instanceof NullExpression) {
            return new Expr\Comparison($field, 'IS', 'NULL');
        }

        $parameterName = $this->generateParameterName();
        $this->queryBuilder->setParameter($parameterName, $expression->dispatch($this), $this->fieldType);

        return new Expr\Comparison($field, self::COMPARISON_MAP[$operator], ':' . $parameterName);
    }

    public function walkAll(): mixed
    {
        return null;
    }

    public function walkOrder(string $field, string $direction): mixed
    {
        return new Expr\OrderBy($field, $direction);
    }

    public function walkNot(ExpressionInterface $expression): mixed
    {
        return new Expr\Func('NOT', [$expression->dispatch($this)]);
    }

    /**
     * {@inheritDoc}
     */
    public function walkAnd(array $arguments): mixed
    {
        return new Expr\Andx(array_map(function (ExpressionInterface $expression) {
            return $expression->dispatch($this);
        }, $arguments));
    }

    /**
     * {@inheritDoc}
     */
    public function walkOr(array $arguments): mixed
    {
        return new Expr\Orx(array_map(function (ExpressionInterface $expression) {
            return $expression->dispatch($this);
        }, $arguments));
    }

    public function walkEntry(string $key, ExpressionInterface $expression): mixed
    {
        $walker = new DqlWalker($this->queryBuilder, $this->field . '.' . $key);

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

        assert(is_string($origParamName));
        assert(is_string($parameterName));

        $filter = static function (Parameter $parameter) use (&$parameterName): bool {
            return $parameter->getName() === $parameterName;
        };

        $i = 1;
        while (0 < $params->filter($filter)->count()) {
            $parameterName = $origParamName . '_' . $i++;
        }

        return $parameterName;
    }
}
