<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Expression;

use Solido\QueryLanguage\Expression\Literal\LiteralExpression;
use Solido\QueryLanguage\Walker\TreeWalkerInterface;

use function assert;

final class EntryExpression implements ExpressionInterface
{
    use ExpressionTrait;

    public function __construct(private LiteralExpression $key, private ExpressionInterface $value)
    {
    }

    public static function create(ExpressionInterface $key, ExpressionInterface $value): self
    {
        assert($key instanceof LiteralExpression, self::getShortName() . ' accepts only literal expressions as argument #1. Passed ' . $value);

        return new self($key, $value);
    }

    public function getKey(): LiteralExpression
    {
        return $this->key;
    }

    public function getValue(): ExpressionInterface
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return '$entry(' . $this->key . ', ' . $this->value . ')';
    }

    public function dispatch(TreeWalkerInterface $treeWalker): mixed
    {
        return $treeWalker->walkEntry((string) $this->key, $this->value);
    }
}
