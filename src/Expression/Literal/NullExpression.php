<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Expression\Literal;

final class NullExpression extends LiteralExpression
{
    protected function __construct()
    {
        parent::__construct(null);
    }

    public function __toString(): string
    {
        return 'null';
    }
}
