<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Processor\Doctrine\ORM;

use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Walker\AbstractWalker;

class AnalyzerWalker extends AbstractWalker
{
    private bool $simple;

    public function __construct()
    {
        $this->simple = true;
    }

    /**
     * {@inheritdoc}
     */
    public function walkEntry(string $key, ExpressionInterface $expression)
    {
        $this->simple = false;
    }

    public function isSimple(): bool
    {
        return $this->simple;
    }
}
