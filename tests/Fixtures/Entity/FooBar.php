<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Tests\Fixtures\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="ql_foobar")
 */
#[ORM\Entity]
#[ORM\Table(name: 'ql_foobar')]
class FooBar
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

    /** @ORM\Column() */
    #[ORM\Column]
    public string $foobar = 'foobar';
}
