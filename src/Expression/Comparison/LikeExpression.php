<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Expression\Comparison;

use Solido\QueryLanguage\Expression\Literal\LiteralExpression;

use function Safe\sprintf;

final class LikeExpression extends ComparisonExpression
{
    /**
     * @param LiteralExpression $value
     */
    public function __construct($value)
    {
        parent::__construct($value, 'like');
    }

    public function __toString(): string
    {
        return sprintf('$like(%s)', (string) $this->value);
    }
}
