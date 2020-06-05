<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker\Validation;

use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\Literal\LiteralExpression;
use Solido\QueryLanguage\Expression\ValueExpression;

/**
 * This walker always adds a violation, whatever expresion is passed to it.
 * If extended is useful to build a walker that allows a very specific operation.
 */
class InvalidWalker extends ValidationWalker
{
    /**
     * {@inheritdoc}
     */
    public function walkLiteral(LiteralExpression $expression)
    {
        $this->addViolation('Invalid operation');
    }

    /**
     * {@inheritdoc}
     */
    public function walkComparison(string $operator, ValueExpression $expression)
    {
        $this->addViolation('Invalid operation');
    }

    /**
     * {@inheritdoc}
     */
    public function walkAll()
    {
        $this->addViolation('Invalid operation');
    }

    /**
     * {@inheritdoc}
     */
    public function walkOrder(string $field, string $direction)
    {
        $this->addViolation('Invalid operation');
    }

    /**
     * {@inheritdoc}
     */
    public function walkNot(ExpressionInterface $expression)
    {
        $this->addViolation('Invalid operation');
    }

    /**
     * {@inheritdoc}
     */
    public function walkAnd(array $arguments)
    {
        $this->addViolation('Invalid operation');
    }

    /**
     * {@inheritdoc}
     */
    public function walkOr(array $arguments)
    {
        $this->addViolation('Invalid operation');
    }

    /**
     * {@inheritdoc}
     */
    public function walkEntry(string $key, ExpressionInterface $expression)
    {
        $this->addViolation('Invalid operation');
    }
}
