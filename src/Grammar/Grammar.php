<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Grammar;

use Solido\QueryLanguage\Exception\InvalidArgumentException;
use Solido\QueryLanguage\Expression\AllExpression;
use Solido\QueryLanguage\Expression\Comparison;
use Solido\QueryLanguage\Expression\EntryExpression;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\Logical;
use Solido\QueryLanguage\Expression\OrderExpression;
use function get_class;

final class Grammar extends AbstractGrammar
{
    private static ?self $instance = null;

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
        $expr = parent::parse($input);

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
    protected function unaryExpression(string $type, $value)
    {
        switch ($type) {
            case 'all':
                return new AllExpression();
            case 'not':
                return Logical\NotExpression::create($value);
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
    protected function binaryExpression(string $type, $left, $right)
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
     * @return OrderExpression
     *
     * @phpstan-param 'asc'|'desc' $direction
     */
    protected function orderExpression(string $field, string $direction)
    {
        return new OrderExpression($field, $direction);
    }

    /**
     * {@inheritdoc}
     */
    protected function variadicExpression(string $type, array $arguments)
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
