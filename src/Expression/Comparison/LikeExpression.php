<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Expression\Comparison;

use function sprintf;

final class LikeExpression extends ComparisonExpression
{
    public function __construct(mixed $value)
    {
        parent::__construct($value, 'like');
    }

    public function __toString(): string
    {
        return sprintf('$like(%s)', (string) $this->value);
    }
}
