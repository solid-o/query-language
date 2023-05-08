<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker\Validation;

use MyCLabs\Enum\Enum;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\Literal\LiteralExpression;
use TypeError;

use function class_exists;
use function get_debug_type;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function is_subclass_of;
use function Safe\sprintf;

class EnumWalker extends ValidationWalker
{
    /** @var string[] */
    private array $values;

    /** @param class-string<Enum>|string[] $values */
    public function __construct($values)
    {
        parent::__construct();

        if (is_string($values) && class_exists($values) && is_subclass_of($values, Enum::class, true)) {
            $values = $values::toArray();
        }

        if (! is_array($values)) {
            throw new TypeError(sprintf('Argument 1 passed to %s must be an array or an %s class name. %s given', __METHOD__, Enum::class, get_debug_type($values)));
        }

        $this->values = $values;
    }

    /**
     * {@inheritdoc}
     */
    public function walkLiteral(LiteralExpression $expression)
    {
        $expressionValue = $expression->getValue();
        if (in_array($expressionValue, $this->values, true)) {
            return;
        }

        $this->addViolation('Value "{{ value }}" is not allowed. Must be one of "{{ allowed_values }}".', [
            '{{ value }}' => (string) $expressionValue,
            '{{ allowed_values }}' => implode('", "', $this->values),
        ]);
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
    public function walkEntry(string $key, ExpressionInterface $expression)
    {
        $this->addViolation('Invalid operation');
    }
}
