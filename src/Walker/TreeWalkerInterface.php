<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker;

use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\Literal\LiteralExpression;
use Solido\QueryLanguage\Expression\ValueExpression;

interface TreeWalkerInterface
{
    /**
     * Evaluates a literal expression.
     */
    public function walkLiteral(LiteralExpression $expression): mixed;

    /**
     * Evaluates a comparison expression.
     */
    public function walkComparison(string $operator, ValueExpression $expression): mixed;

    /**
     * Evaluates a match all expression.
     */
    public function walkAll(): mixed;

    /**
     * Evaluates an order expression.
     */
    public function walkOrder(string $field, string $direction): mixed;

    /**
     * Evaluates a NOT expression.
     */
    public function walkNot(ExpressionInterface $expression): mixed;

    /**
     * Evaluates an AND expression.
     *
     * @param ExpressionInterface[] $arguments
     */
    public function walkAnd(array $arguments): mixed;

    /**
     * Evaluates an OR expression.
     *
     * @param ExpressionInterface[] $arguments
     */
    public function walkOr(array $arguments): mixed;

    /**
     * Evaluates an ENTRY expression.
     */
    public function walkEntry(string $key, ExpressionInterface $expression): mixed;
}
