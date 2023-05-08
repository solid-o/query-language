<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Expression;

use Solido\QueryLanguage\Exception\InvalidHeaderException;
use Solido\QueryLanguage\Walker\TreeWalkerInterface;

use function array_map;
use function assert;
use function count;
use function explode;
use function Safe\preg_match;
use function Safe\preg_match_all;
use function strtolower;

final class OrderExpression implements ExpressionInterface
{
    private string $field;
    /** @phpstan-var 'asc'|'desc' */
    private string $direction;

    /** @phpstan-param 'asc'|'desc' $direction */
    public function __construct(string $field, string $direction)
    {
        $this->field = $field;
        $this->direction = $direction;
    }

    /**
     * Calculate a new order expresion from the given "X-Order" header.
     */
    public static function fromHeader(string $header): self
    {
        $res = preg_match_all('/(?:[^,"]++(?:"[^"]*+")?)+[^,"]*+/', $header, $matches);
        if (! $res) {
            throw new InvalidHeaderException('Invalid header passed, cannot be parsed');
        }

        $expl = array_map('trim', explode(';', $matches[0][0]));
        if (count($expl) === 1 || ! preg_match('/^(:?a|de)sc$/i', $expl[1])) {
            return new self($expl[0], 'asc');
        }

        $direction = strtolower($expl[1]);
        assert($direction === 'asc' || $direction === 'desc');

        return new self($expl[0], $direction);
    }

    /**
     * Gets the order field.
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * Gets the order direction (asc, desc).
     *
     * @phpstan-return 'asc'|'desc'
     */
    public function getDirection(): string
    {
        return $this->direction;
    }

    public function __toString(): string
    {
        return '$order(' . $this->field . ', ' . $this->direction . ')';
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch(TreeWalkerInterface $treeWalker)
    {
        return $treeWalker->walkOrder($this->field, $this->direction);
    }
}
