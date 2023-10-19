<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker\PhpCr;

use DateTime;
use Doctrine\ODM\PHPCR\Query\Builder\AbstractNode;
use Doctrine\ODM\PHPCR\Query\Builder\ConstraintAndx;
use Doctrine\ODM\PHPCR\Query\Builder\ConstraintChild;
use Doctrine\ODM\PHPCR\Query\Builder\ConstraintComparison;
use Doctrine\ODM\PHPCR\Query\Builder\ConstraintNot;
use Doctrine\ODM\PHPCR\Query\Builder\ConstraintOrx;
use Doctrine\ODM\PHPCR\Query\Builder\OperandDynamicField;
use Doctrine\ODM\PHPCR\Query\Builder\OperandDynamicLocalName;
use Doctrine\ODM\PHPCR\Query\Builder\OperandDynamicLowerCase;
use Doctrine\ODM\PHPCR\Query\Builder\OperandStaticLiteral;
use Doctrine\ODM\PHPCR\Query\Builder\OrderByAdd;
use Doctrine\ODM\PHPCR\Query\Builder\Ordering;
use InvalidArgumentException;
use PHPCR\Query\QOM\QueryObjectModelConstantsInterface as QOMConstants;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\Literal\LiteralExpression;
use Solido\QueryLanguage\Expression\Literal\NullExpression;
use Solido\QueryLanguage\Expression\ValueExpression;
use Solido\QueryLanguage\Walker\AbstractWalker;

use function assert;
use function explode;

class NodeWalker extends AbstractWalker
{
    private const COMPARISON_MAP = [
        '=' => QOMConstants::JCR_OPERATOR_EQUAL_TO,
        '<' => QOMConstants::JCR_OPERATOR_LESS_THAN,
        '<=' => QOMConstants::JCR_OPERATOR_LESS_THAN_OR_EQUAL_TO,
        '>' => QOMConstants::JCR_OPERATOR_GREATER_THAN,
        '>=' => QOMConstants::JCR_OPERATOR_GREATER_THAN_OR_EQUAL_TO,
        'like' => QOMConstants::JCR_OPERATOR_LIKE,
    ];

    public function __construct(private readonly string $field, private readonly string|null $fieldType = null)
    {
    }

    public function walkLiteral(LiteralExpression $expression): mixed
    {
        $value = $expression->getValue();
        if ($expression instanceof NullExpression) {
            return $value;
        }

        if ($this->fieldType === 'date') {
            return new DateTime($value);
        }

        return $value;
    }

    public function walkComparison(string $operator, ValueExpression $expression): mixed
    {
        if ($this->fieldType === 'parent') {
            if ($operator !== '=') {
                throw new InvalidArgumentException('Cannot evaluate child nodes with non equals comparison.');
            }

            assert($expression instanceof LiteralExpression);

            return new ConstraintChild(new DummyNode(), explode('.', $this->field)[0], $this->walkLiteral($expression));
        }

        $comparison = new ConstraintComparison(new DummyNode(), self::COMPARISON_MAP[$operator]);

        $field = $this->field;
        $left = function (AbstractNode $parent) use ($field): AbstractNode {
            return $this->fieldType === 'nodename' ?
                new OperandDynamicLocalName($parent, explode('.', $field)[0]) :
                new OperandDynamicField($parent, $field);
        };

        if ($operator === 'like') {
            $lowerCase = new OperandDynamicLowerCase($comparison);
            $lowerCase->addChild($left($lowerCase));
            $left = $lowerCase;

            $value = '%' . $expression->getValue() . '%';
        } else {
            $left = $left($comparison);
            $value = $expression->getValue();
        }

        $right = new OperandStaticLiteral($comparison, $value);

        $comparison->addChild($left);
        $comparison->addChild($right);

        return $comparison;
    }

    public function walkAll(): mixed
    {
        return null;
    }

    public function walkOrder(string $field, string $direction): mixed
    {
        $left = function (AbstractNode $parent) use ($field): AbstractNode {
            return $this->fieldType === 'nodename' ?
                new OperandDynamicLocalName($parent, explode('.', $field)[0]) :
                new OperandDynamicField($parent, $field);
        };

        $node = new OrderByAdd();
        $ordering = $node->{$direction}();
        assert($ordering instanceof Ordering);
        $ordering->addChild($left($ordering));

        return $node;
    }

    public function walkNot(ExpressionInterface $expression): mixed
    {
        $node = new ConstraintNot();
        $node->addChild($expression->dispatch($this));

        return $node;
    }

    /**
     * {@inheritDoc}
     */
    public function walkAnd(array $arguments): mixed
    {
        $node = new ConstraintAndx();
        foreach ($arguments as $argument) {
            $node->addChild($argument->dispatch($this));
        }

        return $node;
    }

    /**
     * {@inheritDoc}
     */
    public function walkOr(array $arguments): mixed
    {
        $node = new ConstraintOrx();
        foreach ($arguments as $argument) {
            $node->addChild($argument->dispatch($this));
        }

        return $node;
    }

    public function walkEntry(string $key, ExpressionInterface $expression): mixed
    {
        $walker = new NodeWalker($this->field . '.' . $key);

        return $expression->dispatch($walker);
    }
}
