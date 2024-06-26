<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker\Validation;

use BackedEnum;
use MyCLabs\Enum\Enum;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\Literal\LiteralExpression;
use TypeError;
use UnitEnum;

use function array_map;
use function class_exists;
use function get_debug_type;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function is_subclass_of;
use function sprintf;

class EnumWalker extends ValidationWalker
{
    /** @var string[] */
    private array $values;

    /** @param class-string<Enum|UnitEnum|BackedEnum>|string[] $values */
    public function __construct(string|array $values)
    {
        parent::__construct();

        if (is_string($values) && class_exists($values)) {
            if (is_subclass_of($values, Enum::class, true)) {
                $values = $values::toArray();
            } elseif (is_subclass_of($values, UnitEnum::class, true)) {
                $values = array_map(static fn ($case) => $case instanceof BackedEnum ? $case->value : $case->name, $values::cases());
            }
        }

        if (! is_array($values)) {
            throw new TypeError(sprintf('Argument 1 passed to %s must be an array or an %s class name. %s given', __METHOD__, Enum::class, get_debug_type($values)));
        }

        $this->values = $values;
    }

    public function walkLiteral(LiteralExpression $expression): mixed
    {
        $expressionValue = $expression->getValue();
        if (in_array($expressionValue, $this->values, true)) {
            return null;
        }

        $this->addViolation('Value "{{ value }}" is not allowed. Must be one of "{{ allowed_values }}".', [
            '{{ value }}' => (string) $expressionValue,
            '{{ allowed_values }}' => implode('", "', $this->values),
        ]);

        return null;
    }

    public function walkOrder(string $field, string $direction): mixed
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
