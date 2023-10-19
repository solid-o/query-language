<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker\Validation;

use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\Literal\LiteralExpression;
use Solido\QueryLanguage\Expression\ValueExpression;

use function implode;
use function in_array;

class OrderWalker extends ValidationWalker
{
    /** @param string[] $orderableFields */
    public function __construct(private readonly array $orderableFields)
    {
        parent::__construct();
    }

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
        if (in_array($field, $this->orderableFields, true)) {
            return null;
        }

        $this->addViolation('Value "{{ value }}" is not allowed. Must be one of "{{ allowed_values }}".', [
            '{{ value }}' => $field,
            '{{ allowed_values }}' => implode('", "', $this->orderableFields),
        ]);

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
