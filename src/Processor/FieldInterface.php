<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Processor;

use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Walker\Validation\ValidationWalkerInterface;

/** @property string $fieldName */
interface FieldInterface
{
    /**
     * Adds condition to query builder.
     */
    public function addCondition(object $queryBuilder, ExpressionInterface $expression): void;

    /**
     * Gets the validation walker factory for the current field.
     */
    public function getValidationWalker(): ValidationWalkerInterface|string|callable|null;
}
