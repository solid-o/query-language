<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Tests\Fixtures\Document;

use Doctrine\ODM\PHPCR\Mapping\Annotations as PHPCR;

/**
 * @PHPCR\Document(referenceable=true)
 */
class FooBar
{
    /** @PHPCR\Id(strategy="ASSIGNED") */
    public string $id = '';

    /** @PHPCR\Uuid() */
    public string $uuid = '';

    /** @PHPCR\Field() */
    public string $foobar = 'foobar';
}
