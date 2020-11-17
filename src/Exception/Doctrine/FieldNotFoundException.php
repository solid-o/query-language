<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Exception\Doctrine;

use DomainException;
use Solido\QueryLanguage\Exception\ExceptionInterface;

use function Safe\sprintf;

class FieldNotFoundException extends DomainException implements ExceptionInterface
{
    public function __construct(string $fieldName, string $className)
    {
        parent::__construct(sprintf('Field "%s" could not be found in %s mapping.', $fieldName, $className));
    }
}
