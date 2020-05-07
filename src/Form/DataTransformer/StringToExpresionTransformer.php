<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Form\DataTransformer;

use Solido\QueryLanguage\Exception\SyntaxError;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Grammar\Grammar;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use function is_string;

class StringToExpresionTransformer implements DataTransformerInterface
{
    private Grammar $grammar;

    public function __construct(?Grammar $grammar = null)
    {
        $this->grammar = $grammar ?? Grammar::getInstance();
    }

    /**
     * {@inheritdoc}
     */
    public function transform($value): string
    {
        if ($value === null) {
            return '';
        }

        if (! $value instanceof ExpressionInterface) {
            throw new TransformationFailedException('Expected ' . ExpressionInterface::class);
        }

        return (string) $value;
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($value): ?ExpressionInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof ExpressionInterface) {
            return $value;
        }

        if (! is_string($value)) {
            throw new TransformationFailedException('Expected string');
        }

        try {
            return $this->grammar->parse($value);
        } catch (SyntaxError $e) {
            throw new TransformationFailedException('Invalid value', 0, $e);
        }
    }
}
