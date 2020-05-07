<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Expression\Literal;

final class StringExpression extends LiteralExpression
{
    protected function __construct(string $value)
    {
        parent::__construct($value);
    }
}
