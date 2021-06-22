<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Processor;

use Solido\QueryLanguage\Expression\OrderExpression;

interface OrderableFieldInterface extends FieldInterface
{
    /**
     * Adds order by query part.
     *
     * @return string[]
     * @phpstan-return array{string, 'asc'|'desc'}
     */
    public function getOrder(object $queryBuilder, OrderExpression $orderExpression): array;
}
