<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Expression\Literal;

final class IntegerExpression extends NumericExpression
{
    protected function __construct(int $value)
    {
        parent::__construct($value);
    }
}
