<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Exception;

final class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface
{
    public function setMessage(string $message): void
    {
        $this->message = $message;
    }
}
