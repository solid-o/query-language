<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Expression;

use Solido\QueryLanguage\Walker\TreeWalkerInterface;

interface ExpressionInterface
{
    /**
     * Returns expression as string.
     */
    public function __toString(): string;

    /**
     * Dispatches the expression to the tree walker.
     *
     * @return mixed
     */
    public function dispatch(TreeWalkerInterface $treeWalker);
}
