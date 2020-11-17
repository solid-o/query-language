<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Expression\Logical;

use Solido\QueryLanguage\Expression\AllExpression;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Walker\TreeWalkerInterface;

use function array_values;
use function count;
use function implode;

final class OrExpression implements LogicalExpressionInterface
{
    /** @var ExpressionInterface[] */
    private array $arguments;

    /**
     * @param ExpressionInterface[] $arguments
     */
    private function __construct(array $arguments)
    {
        $this->arguments = $arguments;
    }

    public function __toString(): string
    {
        return '$or(' . implode(', ', $this->arguments) . ')';
    }

    /**
     * @param ExpressionInterface[] $arguments
     */
    public static function create(array $arguments): ExpressionInterface
    {
        foreach ($arguments as $argument) {
            if ($argument instanceof AllExpression) {
                return new AllExpression();
            }
        }

        $arguments = array_values($arguments);
        switch (count($arguments)) {
            case 0:
                return new AllExpression();

            case 1:
                return $arguments[0];

            default:
                return new self($arguments);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch(TreeWalkerInterface $treeWalker)
    {
        return $treeWalker->walkOr($this->arguments);
    }
}
