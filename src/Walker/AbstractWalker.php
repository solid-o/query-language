<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker;

use Solido\QueryLanguage\Expression\Literal\LiteralExpression;

abstract class AbstractWalker implements TreeWalkerInterface
{
    /**
     * Field name.
     */
    protected string $field;

    public function __construct(string $field)
    {
        $this->field = $field;
    }

    /**
     * {@inheritdoc}
     */
    public function walkLiteral(LiteralExpression $expression)
    {
        return $expression->getValue();
    }
}
