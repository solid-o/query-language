<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Exception;

use RuntimeException;
use Throwable;
use function max;
use function min;
use function Safe\substr;
use function strlen;
use function trim;

class SyntaxError extends RuntimeException implements ExceptionInterface
{
    public function __construct(string $buffer = '', int $position = 0, ?Throwable $previous = null)
    {
        $start = max($position - 7, 0);
        $end = min($position + 20, strlen($buffer));
        $excerpt = substr($buffer, $start, $end - $start);

        parent::__construct('Syntax Error: could not parse "' . trim($excerpt) . '"', 0, $previous);
    }
}
