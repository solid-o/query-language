<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Expression;

use Solido\QueryLanguage\Walker\TreeWalkerInterface;

final class AllExpression implements ExpressionInterface
{
    public function __toString(): string
    {
        return '$all()';
    }

    public function dispatch(TreeWalkerInterface $treeWalker): mixed
    {
        return $treeWalker->walkAll();
    }
}
