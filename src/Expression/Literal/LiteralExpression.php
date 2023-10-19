<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Expression\Literal;

use Solido\QueryLanguage\Expression\ValueExpression;
use Solido\QueryLanguage\Walker\TreeWalkerInterface;
use TypeError;

use function gettype;
use function is_numeric;
use function is_object;
use function is_string;
use function Safe\preg_match;
use function sprintf;

abstract class LiteralExpression extends ValueExpression
{
    public function dispatch(TreeWalkerInterface $treeWalker): mixed
    {
        return $treeWalker->walkLiteral($this);
    }

    public static function create(mixed $value): LiteralExpression
    {
        if (! is_string($value)) {
            throw new TypeError(sprintf('Argument 1 passed to ' . __METHOD__ . ' must be a string. %s passed', is_object($value) ? $value::class : gettype($value)));
        }

        switch (true) {
            case $value === 'true':
            case $value === 'false':
                return new BooleanExpression($value === 'true');

            case $value === 'null':
                return new NullExpression();

            case preg_match('/^\d+$/', $value) === 1:
                return new IntegerExpression((int) $value);

            case is_numeric($value):
                return new NumericExpression($value);

            default:
                return new StringExpression($value);
        }
    }
}
