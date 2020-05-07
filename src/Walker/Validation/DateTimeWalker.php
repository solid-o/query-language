<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker\Validation;

use Safe\DateTimeImmutable;
use Solido\QueryLanguage\Expression\Literal\LiteralExpression;
use Throwable;

class DateTimeWalker extends ValidationWalker
{
    /**
     * {@inheritdoc}
     */
    public function walkLiteral(LiteralExpression $expression)
    {
        try {
            new DateTimeImmutable($expression->getValue());
        } catch (Throwable $e) { // @phpstan-ignore-line
            $this->addViolation('{{ value }} is not a valid date time', [
                '{{ value }}' => (string) $expression->getValue(),
            ]);
        }
    }
}
