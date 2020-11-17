<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Form\DataTransformer;

use Solido\Pagination\Exception\InvalidTokenException;
use Solido\Pagination\PageToken;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

use function is_string;

class PageTokenTransformer implements DataTransformerInterface
{
    /**
     * {@inheritdoc}
     */
    public function transform($value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof PageToken) {
            return (string) $value;
        }

        if (is_string($value) && PageToken::isValid($value)) {
            return $value;
        }

        throw new TransformationFailedException('Invalid token provided');
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($value): ?PageToken
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof PageToken) {
            return $value;
        }

        try {
            return PageToken::parse($value);
        } catch (InvalidTokenException $e) {
            throw new TransformationFailedException('Invalid token provided', 0, $e);
        }
    }
}
