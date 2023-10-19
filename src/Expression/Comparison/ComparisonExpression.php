<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Expression\Comparison;

use Solido\QueryLanguage\Expression\ExpressionTrait;
use Solido\QueryLanguage\Expression\Literal\LiteralExpression;
use Solido\QueryLanguage\Walker\TreeWalkerInterface;

use function assert;

abstract class ComparisonExpression implements ComparisonExpressionInterface
{
    use ExpressionTrait;

    protected LiteralExpression $value;

    /**
     * Can be <, <=, >, >=, =.
     */
    protected string $operator;

    public function __construct(mixed $value, string $operator)
    {
        assert($value instanceof LiteralExpression, self::getShortName() . ' accepts only literal expressions as argument #1. Passed ' . $value);

        $this->value = $value;
        $this->operator = $operator;
    }

    /**
     * Gets the comparison value.
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Gets the operator.
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    public function dispatch(TreeWalkerInterface $treeWalker): mixed
    {
        return $treeWalker->walkComparison($this->operator, $this->value);
    }
}
