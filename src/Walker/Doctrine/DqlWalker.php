<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker\Doctrine;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use Safe\DateTime;
use Safe\DateTimeImmutable;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\Literal\LiteralExpression;
use Solido\QueryLanguage\Expression\Literal\NullExpression;
use Solido\QueryLanguage\Expression\Literal\StringExpression;
use Solido\QueryLanguage\Expression\ValueExpression;
use Solido\QueryLanguage\Walker\AbstractWalker;

use function array_map;
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

    protected QueryBuilder $queryBuilder;
    private ?string $fieldType;

    public function __construct(QueryBuilder $queryBuilder, string $field, ?string $fieldType = null)
    {
        parent::__construct($field);

        $this->queryBuilder = $queryBuilder;
        $this->fieldType = $fieldType;
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

    /**
     * {@inheritdoc}
     */
    public function walkComparison(string $operator, ValueExpression $expression)
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
        return new Expr\OrderBy($field, $direction);
    }

    /**
     * {@inheritdoc}
     */
    public function walkNot(ExpressionInterface $expression)
    {
        return new Expr\Func('NOT', [$expression->dispatch($this)]);
    }

    /**
     * {@inheritdoc}
     */
    public function walkAnd(array $arguments)
    {
        return new Expr\Andx(array_map(function (ExpressionInterface $expression) {
            return $expression->dispatch($this);
        }, $arguments));
    }

    /**
     * {@inheritdoc}
     */
    public function walkOr(array $arguments)
    {
        return new Expr\Orx(array_map(function (ExpressionInterface $expression) {
            return $expression->dispatch($this);
        }, $arguments));
    }

    /**
     * {@inheritdoc}
     */
    public function walkEntry(string $key, ExpressionInterface $expression)
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
        $underscoreField = mb_strtolower(
            preg_replace('/(?|(?<=[a-z0-9])([A-Z])|(?<=[A-Z]{2})([a-z]))/', '_$1', $this->field)
        );
        $parameterName = $origParamName = preg_replace('/\W+/', '_', $underscoreField);

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
