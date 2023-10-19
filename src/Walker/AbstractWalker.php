<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker;

use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\Literal\LiteralExpression;
use Solido\QueryLanguage\Expression\ValueExpression;

abstract class AbstractWalker implements TreeWalkerInterface
{
    public function walkLiteral(LiteralExpression $expression): mixed
    {
        return null;
    }

    public function walkComparison(string $operator, ValueExpression $expression): mixed
    {
        if (! $expression instanceof LiteralExpression) {
            return null;
        }

        $this->walkLiteral($expression);

        return null;
    }

    public function walkAll(): mixed
    {
        return null;
    }

    public function walkOrder(string $field, string $direction): mixed
    {
        return null;
    }

    public function walkNot(ExpressionInterface $expression): mixed
    {
        $expression->dispatch($this);

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function walkAnd(array $arguments): mixed
    {
        foreach ($arguments as $expression) {
            $expression->dispatch($this);
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function walkOr(array $arguments): mixed
    {
        foreach ($arguments as $expression) {
            $expression->dispatch($this);
        }

        return null;
    }

    public function walkEntry(string $key, ExpressionInterface $expression): mixed
    {
        $expression->dispatch($this);

        return null;
    }
}
