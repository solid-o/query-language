<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Tests\Fixtures\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use function strlen;

/**
 * @ORM\Entity()
 */
#[ORM\Entity]
class User
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id;

    /** @ORM\Column(type="string") */
    #[ORM\Column(type: Types::STRING)]
    public string $name;

    /** @ORM\ManyToOne(targetEntity=FooBar::class, cascade={"persist", "remove"}) */
    #[ORM\ManyToOne(targetEntity: FooBar::class, cascade: ['persist', 'remove'])]
    public FooBar $foobar;

    /** @ORM\ManyToMany(targetEntity=Group::class, inversedBy="users") */
    #[ORM\ManyToMany(targetEntity: Group::class, inversedBy: 'users')]
    public Collection $groups;

    /** @ORM\Column(type="integer") */
    #[ORM\Column(type: Types::INTEGER)]
    public int $nameLength;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->nameLength = strlen($name);

        $this->foobar = new FooBar();
        $this->foobar->foobar .= '_' . $name;
        $this->groups = new ArrayCollection();
    }
}
