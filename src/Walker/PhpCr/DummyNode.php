<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker\PhpCr;

use Doctrine\ODM\PHPCR\Query\Builder\AbstractNode;

/**
 * Used only to build valid query objects in NodeWalker.
 * Please DO NOT use it.
 *
 * @internal
 */
class DummyNode extends AbstractNode
{
    public function getNodeType(): string
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function getCardinalityMap(): array
    {
        return [];
    }
}
