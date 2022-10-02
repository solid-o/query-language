<?php

namespace Solido\QueryLanguage\Tests\Fixtures\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table("u_group")
 */
class Group
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\Column(type="integer")
     */
    public int $id;

    /** @ORM\ManyToMany(targetEntity=User::class, mappedBy="groups") */
    public Collection $users;

    /** @ORM\Column() */
    public string $name;

    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
