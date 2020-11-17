<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Expression\Comparison;

use Solido\QueryLanguage\Expression\Literal\LiteralExpression;

use function Safe\sprintf;

final class EqualExpression extends ComparisonExpression
{
    /**
     * @param LiteralExpression $value
     */
    public function __construct($value)
    {
        parent::__construct($value, '=');
    }

    public function __toString(): string
    {
        return sprintf('$eq(%s)', (string) $this->value);
    }
}
