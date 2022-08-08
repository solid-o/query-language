<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker\Validation;

use Solido\QueryLanguage\Walker\AbstractWalker;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ValidationWalker extends AbstractWalker implements ValidationWalkerInterface
{
    protected ?ExecutionContextInterface $validationContext;

    public function __construct()
    {
        $this->validationContext = null;
    }

    public function setValidationContext(ExecutionContextInterface $context): void
    {
        $this->validationContext = $context;
    }

    /**
     * @param mixed[] $parameters
     */
    protected function addViolation(string $message, array $parameters = []): void
    {
        if ($this->validationContext === null) {
            return;
        }

        $this->validationContext
            ->buildViolation($message, $parameters)
            ->addViolation();
    }
}
