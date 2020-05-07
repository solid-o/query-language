<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Processor;

use Solido\QueryLanguage\Expression\ExpressionInterface;

/**
 * @property string $fieldName
 */
interface ColumnInterface
{
    /**
     * Adds condition to query builder.
     */
    public function addCondition(object $queryBuilder, ExpressionInterface $expression): void;

    /**
     * Gets the validation walker factory for the current column.
     *
     * @return string|callable|null
     */
    public function getValidationWalker();
}
