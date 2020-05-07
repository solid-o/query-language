<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Expression;

use Solido\QueryLanguage\Walker\TreeWalkerInterface;

final class OrderExpression implements ExpressionInterface
{
    private string $field;
    /** @phpstan-var 'asc'|'desc' */
    private string $direction;

    /**
     * @phpstan-param 'asc'|'desc' $direction
     */
    public function __construct(string $field, string $direction)
    {
        $this->field = $field;
        $this->direction = $direction;
    }

    /**
     * Gets the order column.
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * Gets the order direction (asc, desc).
     *
     * @phpstan-return 'asc'|'desc'
     */
    public function getDirection(): string
    {
        return $this->direction;
    }

    public function __toString(): string
    {
        return '$order(' . $this->field . ', ' . $this->direction . ')';
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch(TreeWalkerInterface $treeWalker)
    {
        return $treeWalker->walkOrder($this->field, $this->direction);
    }
}
