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
     *
     * @return mixed
     */
    public function walkLiteral(LiteralExpression $expression);

    /**
     * Evaluates a comparison expression.
     *
     * @return mixed
     */
    public function walkComparison(string $operator, ValueExpression $expression);

    /**
     * Evaluates a match all expression.
     *
     * @return mixed
     */
    public function walkAll();

    /**
     * Evaluates an order expression.
     *
     * @return mixed
     */
    public function walkOrder(string $field, string $direction);

    /**
     * Evaluates a NOT expression.
     *
     * @return mixed
     */
    public function walkNot(ExpressionInterface $expression);

    /**
     * Evaluates an AND expression.
     *
     * @param ExpressionInterface[] $arguments
     *
     * @return mixed
     */
    public function walkAnd(array $arguments);

    /**
     * Evaluates an OR expression.
     *
     * @param ExpressionInterface[] $arguments
     *
     * @return mixed
     */
    public function walkOr(array $arguments);

    /**
     * Evaluates an ENTRY expression.
     *
     * @return mixed
     */
    public function walkEntry(string $key, ExpressionInterface $expression);
}
