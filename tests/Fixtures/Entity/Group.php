<?php

namespace Solido\QueryLanguage\Tests\Fixtures\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table("u_group")
 */
#[ORM\Entity]
#[ORM\Table(name: 'u_group')]
class Group
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="integer")
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    public int $id;

    /** @ORM\ManyToMany(targetEntity=User::class, mappedBy="groups") */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'groups')]
    public Collection $users;

    /** @ORM\Column() */
    #[ORM\Column]
    public string $name;

    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
