<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Expression;

use ReflectionClass;

use function assert;
use function is_string;
use function Safe\preg_replace;

trait ExpressionTrait
{
    private static function getShortName(): string
    {
        $reflClass = new ReflectionClass(self::class);
        $expr = preg_replace('/Expression$/', '', $reflClass->getShortName());
        assert(is_string($expr));

        return $expr;
    }
}
