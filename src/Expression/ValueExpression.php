<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Expression;

use Solido\QueryLanguage\Walker\TreeWalkerInterface;

class ValueExpression implements ExpressionInterface
{
    /**
     * The value represented by the literal expression.
     */
    protected mixed $value;

    protected function __construct(mixed $value)
    {
        $this->value = $value;
    }

    /**
     * Gets the literal expression value.
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    public function dispatch(TreeWalkerInterface $treeWalker): mixed
    {
        return $this->getValue();
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }

    /**
     * Creates a new value expression.
     *
     * @return ValueExpression
     */
    public static function create(mixed $value): self
    {
        return new self($value);
    }
}
