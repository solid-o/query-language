<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Expression;

use ReflectionClass;

use function Safe\preg_replace;

trait ExpressionTrait
{
    private static function getShortName(): string
    {
        $reflClass = new ReflectionClass(self::class);

        return preg_replace('/Expression$/', '', $reflClass->getShortName());
    }
}
