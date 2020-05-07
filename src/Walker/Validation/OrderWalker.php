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
    /** @var string[] */
    private array $orderableColumns;

    /**
     * @param string[] $orderableColumns
     */
    public function __construct(array $orderableColumns)
    {
        $this->orderableColumns = $orderableColumns;
    }

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
        if (in_array($field, $this->orderableColumns, true)) {
            return;
        }

        $this->addViolation('Value "{{ value }}" is not allowed. Must be one of "{{ allowed_values }}".', [
            '{{ value }}' => $field,
            '{{ allowed_values }}' => implode('", "', $this->orderableColumns),
        ]);
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
