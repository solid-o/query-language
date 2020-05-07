<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use function strlen;

/**
 * @ORM\Entity()
 */
class User
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    public ?int $id;

    /** @ORM\Column(type="string") */
    public string $name;

    /** @ORM\ManyToOne(targetEntity=FooBar::class, cascade={"persist", "remove"}) */
    public FooBar $foobar;

    /** @ORM\Column(type="integer") */
    public int $nameLength;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->nameLength = strlen($name);

        $this->foobar = new FooBar();
        $this->foobar->foobar .= '_' . $name;
    }
}
