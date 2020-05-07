<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker\Validation;

use Solido\QueryLanguage\Walker\TreeWalkerInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

interface ValidationWalkerInterface extends TreeWalkerInterface
{
    /**
     * Sets the execution context.
     */
    public function setValidationContext(ExecutionContextInterface $context): void;
}
