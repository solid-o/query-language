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

use function get_class;

final class Grammar extends AbstractGrammar
{
    private static ?self $instance = null;
    private ?CacheItemPoolInterface $cache;

    public function __construct(?CacheItemPoolInterface $cache = null)
    {
        parent::__construct();
        $this->cache = $cache;
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
    public function parse(string $input, $accept = ExpressionInterface::class): ExpressionInterface
    {
        $item = $this->cache !== null ? $this->cache->getItem(md5($input)) : null;
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

        throw new InvalidArgumentException(get_class($expr) . ' is not acceptable');
    }

    /**
     * {@inheritdoc}
     */
    protected function unaryExpression(string $type, $value): ExpressionInterface
    {
        switch ($type) {
            case 'all':
                return new AllExpression();

            case 'not':
                return Logical\NotExpression::create($value);

            case 'exists':
                return Logical\NotExpression::create(new Comparison\EqualExpression(LiteralExpression::create('null')));

            case 'eq':
                return new Comparison\EqualExpression($value);

            case 'neq':
                return Logical\NotExpression::create(new Comparison\EqualExpression($value));

            case 'lt':
                return new Comparison\LessThanExpression($value);

            case 'lte':
                return new Comparison\LessThanOrEqualExpression($value);

            case 'gt':
                return new Comparison\GreaterThanExpression($value);

            case 'gte':
                return new Comparison\GreaterThanOrEqualExpression($value);

            case 'like':
                return new Comparison\LikeExpression($value);

            default:
                throw new InvalidArgumentException('Unknown unary operator "' . $type . '"');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function binaryExpression(string $type, $left, $right): ExpressionInterface
    {
        switch ($type) {
            case 'range':
                return Logical\AndExpression::create([new Comparison\GreaterThanOrEqualExpression($left), new Comparison\LessThanOrEqualExpression($right)]);

            case 'entry':
                return EntryExpression::create($left, $right);

            default:
                throw new InvalidArgumentException('Unknown binary operator "' . $type . '"');
        }
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
     * {@inheritdoc}
     */
    protected function variadicExpression(string $type, array $arguments): ExpressionInterface
    {
        switch ($type) {
            case 'and':
                return Logical\AndExpression::create($arguments);

            case 'in':
            case 'or':
                return Logical\OrExpression::create($arguments);

            default:
                throw new InvalidArgumentException('Unknown operator "' . $type . '"');
        }
    }
}
