<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Expression\Comparison;

use function sprintf;

final class LessThanOrEqualExpression extends ComparisonExpression
{
    public function __construct(mixed $value)
    {
        parent::__construct($value, '<=');
    }

    public function __toString(): string
    {
        return sprintf('$lte(%s)', (string) $this->value);
    }
}
