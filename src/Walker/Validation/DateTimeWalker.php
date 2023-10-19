<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker\Validation;

use DateTimeImmutable;
use Solido\QueryLanguage\Expression\Literal\LiteralExpression;
use Throwable;

class DateTimeWalker extends ValidationWalker
{
    public function walkLiteral(LiteralExpression $expression): mixed
    {
        try {
            new DateTimeImmutable($expression->getValue());
        } catch (Throwable) { // @phpstan-ignore-line
            $this->addViolation('{{ value }} is not a valid date time', [
                '{{ value }}' => (string) $expression->getValue(),
            ]);
        }

        return null;
    }
}
