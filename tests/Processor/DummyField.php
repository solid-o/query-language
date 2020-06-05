<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Tests\Processor;

use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Processor\Doctrine\FieldInterface;
use Solido\QueryLanguage\Walker\Validation\ValidationWalker;

class DummyField implements FieldInterface
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
