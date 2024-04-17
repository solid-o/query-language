<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Tests\Fixtures\Document;

use Doctrine\ODM\PHPCR\Mapping\Annotations as PHPCR;
use Doctrine\ODM\PHPCR\Mapping\Attributes as PHPCRAttributes;

use function mt_rand;
use function strlen;

/**
 * @PHPCR\Document(referenceable=true)
 */
#[PHPCRAttributes\Document(referenceable: true)]
class User
{
    /** @PHPCR\Id(strategy="PARENT") */
    #[PHPCRAttributes\Id(strategy: 'PARENT')]
    public ?string $id;

    /**
     * @PHPCR\ParentDocument()
     *
     * @var mixed
     */
    #[PHPCRAttributes\ParentDocument]
    private $parent;

    /** @PHPCR\Nodename() */
    #[PHPCRAttributes\Nodename]
    public string $name;

    /** @PHPCR\ReferenceOne(targetDocument=FooBar::class, strategy="hard", cascade={"persist", "remove"}) */
    #[PHPCRAttributes\ReferenceOne(targetDocument: FooBar::class, strategy: 'hard', cascade: ['persist', 'remove'])]
    public FooBar $foobar;

    /** @PHPCR\Field(type="int") */
    #[PHPCRAttributes\Field(type: 'int')]
    public int $nameLength;

    public function __construct(string $name, $parent = null)
    {
        $this->id = null;
        $this->parent = null;
        $this->name = $name;
        $this->nameLength = strlen($name);
        if ($parent === null) {
            $this->id = '/' . $name;
        } else {
            $this->parent = $parent;
        }

        $this->foobar = new FooBar();
        $this->foobar->id = '/' . $name . '_' . mt_rand();
        $this->foobar->foobar .= '_' . $name;
    }
}
