<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Expression\Logical;

use Solido\QueryLanguage\Exception\LogicException;
use Solido\QueryLanguage\Expression\Comparison\ComparisonExpressionInterface;
use Solido\QueryLanguage\Expression\EntryExpression;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Walker\TreeWalkerInterface;

use function get_class;
use function Safe\sprintf;

final class NotExpression implements LogicalExpressionInterface
{
    private ExpressionInterface $argument;

    private function __construct(ExpressionInterface $expression)
    {
        $this->argument = $expression;
    }

    public function __toString(): string
    {
        return '$not(' . $this->argument . ')';
    }

    public static function create(ExpressionInterface $expression): ExpressionInterface
    {
        if ($expression instanceof self) {
            return $expression->argument;
        }

        if ($expression instanceof EntryExpression) {
            return new EntryExpression($expression->getKey(), self::create($expression->getValue()));
        }

        if (! $expression instanceof ComparisonExpressionInterface && ! $expression instanceof LogicalExpressionInterface) {
            throw new LogicException(sprintf('Cannot negate %s expression', get_class($expression)));
        }

        return new self($expression);
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch(TreeWalkerInterface $treeWalker)
    {
        return $treeWalker->walkNot($this->argument);
    }
}
