<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Tests\Processor;

use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Processor\Doctrine\ColumnInterface;
use Solido\QueryLanguage\Walker\Validation\ValidationWalker;

class DummyColumn implements ColumnInterface
{
    public function addCondition($queryBuilder, ExpressionInterface $expression): void
    {
        // Do nothing.
    }

    public function getValidationWalker()
    {
        return new ValidationWalker();
    }
}
