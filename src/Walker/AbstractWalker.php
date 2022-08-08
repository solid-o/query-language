<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker;

use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\Literal\LiteralExpression;
use Solido\QueryLanguage\Expression\ValueExpression;

abstract class AbstractWalker implements TreeWalkerInterface
{
    /**
     * {@inheritdoc}
     */
    public function walkLiteral(LiteralExpression $expression)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function walkComparison(string $operator, ValueExpression $expression)
    {
        if (! $expression instanceof LiteralExpression) {
            return;
        }

        $this->walkLiteral($expression);
    }

    /**
     * {@inheritdoc}
     */
    public function walkAll()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function walkOrder(string $field, string $direction)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function walkNot(ExpressionInterface $expression)
    {
        $expression->dispatch($this);
    }

    /**
     * {@inheritdoc}
     */
    public function walkAnd(array $arguments)
    {
        foreach ($arguments as $expression) {
            $expression->dispatch($this);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function walkOr(array $arguments)
    {
        foreach ($arguments as $expression) {
            $expression->dispatch($this);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function walkEntry(string $key, ExpressionInterface $expression)
    {
        $expression->dispatch($this);
    }
}
