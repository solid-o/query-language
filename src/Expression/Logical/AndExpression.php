<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Expression\Logical;

use Solido\QueryLanguage\Expression\AllExpression;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Walker\TreeWalkerInterface;

use function array_filter;
use function array_values;
use function count;
use function implode;

final class AndExpression implements LogicalExpressionInterface
{
    /** @param ExpressionInterface[] $arguments */
    private function __construct(private array $arguments)
    {
    }

    public function __toString(): string
    {
        return '$and(' . implode(', ', $this->arguments) . ')';
    }

    /** @param ExpressionInterface[] $arguments */
    public static function create(array $arguments): ExpressionInterface
    {
        $arguments = array_values(
            array_filter($arguments, static fn ($argument) => ! $argument instanceof AllExpression),
        );

        switch (count($arguments)) {
            case 0:
                return new AllExpression();

            case 1:
                return $arguments[0];

            default:
                return new self($arguments);
        }
    }

    public function dispatch(TreeWalkerInterface $treeWalker): mixed
    {
        return $treeWalker->walkAnd($this->arguments);
    }
}
