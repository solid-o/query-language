<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker\Validation;

use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\Literal\LiteralExpression;
use Solido\QueryLanguage\Expression\ValueExpression;

/**
 * This walker always adds a violation, whatever expression is passed to it.
 * If extended is useful to build a walker that allows a very specific operation.
 */
class InvalidWalker extends ValidationWalker
{
    public function walkLiteral(LiteralExpression $expression): mixed
    {
        $this->addViolation('Invalid operation');

        return null;
    }

    public function walkComparison(string $operator, ValueExpression $expression): mixed
    {
        $this->addViolation('Invalid operation');

        return null;
    }

    public function walkAll(): mixed
    {
        $this->addViolation('Invalid operation');

        return null;
    }

    public function walkOrder(string $field, string $direction): mixed
    {
        $this->addViolation('Invalid operation');

        return null;
    }

    public function walkNot(ExpressionInterface $expression): mixed
    {
        $this->addViolation('Invalid operation');

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function walkAnd(array $arguments): mixed
    {
        $this->addViolation('Invalid operation');

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function walkOr(array $arguments): mixed
    {
        $this->addViolation('Invalid operation');

        return null;
    }

    public function walkEntry(string $key, ExpressionInterface $expression): mixed
    {
        $this->addViolation('Invalid operation');

        return null;
    }
}
