<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Expression\Literal;

final class BooleanExpression extends LiteralExpression
{
    protected function __construct(bool $value)
    {
        parent::__construct($value);
    }

    public function __toString(): string
    {
        return $this->value ? 'true' : 'false';
    }
}
