<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Tests\Fixtures\Document;

use Doctrine\ODM\PHPCR\Mapping\Annotations as PHPCR;
use Doctrine\ODM\PHPCR\Mapping\Attributes as PHPCRAttributes;

/**
 * @PHPCR\Document(referenceable=true)
 */
#[PHPCRAttributes\Document(referenceable: true)]
class FooBar
{
    /** @PHPCR\Id(strategy="ASSIGNED") */
    #[PHPCRAttributes\Id(strategy: 'ASSIGNED')]
    public string $id = '';

    /** @PHPCR\Uuid() */
    #[PHPCRAttributes\Uuid]
    public string $uuid = '';

    /** @PHPCR\Field() */
    #[PHPCRAttributes\Field]
    public string $foobar = 'foobar';
}
