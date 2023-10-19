<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker\Validation;

use Solido\QueryLanguage\Expression\Literal\LiteralExpression;

use function is_numeric;

class NumericWalker extends ValidationWalker
{
    public function walkLiteral(LiteralExpression $expression): mixed
    {
        if (! is_numeric($expression->getValue())) {
            $this->addViolation('"{{ value }}" is not numeric.', [
                '{{ value }}' => (string) $expression,
            ]);
        }

        return parent::walkLiteral($expression);
    }
}
