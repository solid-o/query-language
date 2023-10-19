<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Grammar;

use Psr\Cache\CacheItemPoolInterface;
use Solido\QueryLanguage\Exception\InvalidArgumentException;
use Solido\QueryLanguage\Expression\AllExpression;
use Solido\QueryLanguage\Expression\Comparison;
use Solido\QueryLanguage\Expression\EntryExpression;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\Literal\LiteralExpression;
use Solido\QueryLanguage\Expression\Logical;
use Solido\QueryLanguage\Expression\OrderExpression;

use function assert;
use function md5;

final class Grammar extends AbstractGrammar
{
    private static self|null $instance = null;

    public function __construct(private readonly CacheItemPoolInterface|null $cache = null)
    {
        parent::__construct();
    }

    /**
     * Gets the Grammar class singleton.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Parses an expression into an AST.
     *
     * @param class-string<ExpressionInterface>|class-string<ExpressionInterface>[] $accept
     */
    public function parse(string $input, string|array $accept = ExpressionInterface::class): ExpressionInterface
    {
        $item = $this->cache?->getItem(md5($input));
        if (isset($item) && $item->isHit()) {
            $expr = $item->get();
        } else {
            $expr = parent::parse($input);
            if (isset($item)) {
                $item->set($expr);
                assert($this->cache !== null);
                $this->cache->saveDeferred($item);
            }
        }

        foreach ((array) $accept as $acceptedClass) {
            if ($expr instanceof $acceptedClass) {
                return $expr;
            }
        }

        throw new InvalidArgumentException($expr::class . ' is not acceptable');
    }

    /**
     * {@inheritDoc}
     */
    protected function unaryExpression(string $type, $value): ExpressionInterface
    {
        return match ($type) {
            'all' => new AllExpression(),
            'not' => Logical\NotExpression::create($value),
            'exists' => Logical\NotExpression::create(new Comparison\EqualExpression(LiteralExpression::create('null'))),
            'eq' => new Comparison\EqualExpression($value),
            'neq' => Logical\NotExpression::create(new Comparison\EqualExpression($value)),
            'lt' => new Comparison\LessThanExpression($value),
            'lte' => new Comparison\LessThanOrEqualExpression($value),
            'gt' => new Comparison\GreaterThanExpression($value),
            'gte' => new Comparison\GreaterThanOrEqualExpression($value),
            'like' => new Comparison\LikeExpression($value),
            default => throw new InvalidArgumentException('Unknown unary operator "' . $type . '"'),
        };
    }

    /**
     * {@inheritDoc}
     */
    protected function binaryExpression(string $type, $left, $right): ExpressionInterface
    {
        return match ($type) {
            'range' => Logical\AndExpression::create([new Comparison\GreaterThanOrEqualExpression($left), new Comparison\LessThanOrEqualExpression($right)]),
            'entry' => EntryExpression::create($left, $right),
            default => throw new InvalidArgumentException('Unknown binary operator "' . $type . '"'),
        };
    }

    /**
     * Evaluates an order expression.
     *
     * @phpstan-param 'asc'|'desc' $direction
     */
    protected function orderExpression(string $field, string $direction): ExpressionInterface
    {
        return new OrderExpression($field, $direction);
    }

    /**
     * {@inheritDoc}
     */
    protected function variadicExpression(string $type, array $arguments): ExpressionInterface
    {
        return match ($type) {
            'and' => Logical\AndExpression::create($arguments),
            'in', 'or' => Logical\OrExpression::create($arguments),
            default => throw new InvalidArgumentException('Unknown operator "' . $type . '"'),
        };
    }
}
