<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Tests\Walker\Doctrine;

use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Solido\QueryLanguage\Expression\Literal\LiteralExpression;
use Solido\QueryLanguage\Expression\ValueExpression;
use Solido\QueryLanguage\Tests\Doctrine\ORM\FixturesTrait;
use Solido\QueryLanguage\Tests\Fixtures\Entity as QueryLanguageFixtures;
use Solido\QueryLanguage\Walker\Doctrine\DqlWalker;

class DqlWalkerTest extends TestCase
{
    use FixturesTrait;

    private DqlWalker $walker;
    private QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $userRepository = self::$entityManager->getRepository(QueryLanguageFixtures\User::class);
        $this->queryBuilder = $userRepository->createQueryBuilder('u');
        $this->walker = new DqlWalker($this->queryBuilder, 'u.name');
    }

    public function testShouldBuildEqualComparison(): void
    {
        $this->queryBuilder->andWhere(
            $this->walker->walkComparison('=', LiteralExpression::create('foo'))
        );

        self::assertEquals('SELECT u FROM Solido\QueryLanguage\Tests\Fixtures\Entity\User u WHERE u.name = :u_name', $this->queryBuilder->getDQL());
        self::assertEquals('foo', $this->queryBuilder->getParameter('u_name')->getValue());
        self::assertEquals(ParameterType::STRING, $this->queryBuilder->getParameter('u_name')->getType());
    }

    public function testShouldBuildEqualComparisonWithRelatedObjects(): void
    {
        $this->walker = new DqlWalker($this->queryBuilder, 'u.foobar');
        $foobar = self::$entityManager->getRepository(QueryLanguageFixtures\FooBar::class)
            ->findOneBy(['foobar' => 'foobar_goofy']);

        self::assertNotNull($foobar);

        $this->queryBuilder->andWhere(
            $this->walker->walkComparison('=', ValueExpression::create($foobar))
        );

        self::assertEquals('SELECT u FROM Solido\QueryLanguage\Tests\Fixtures\Entity\User u WHERE u.foobar = :u_foobar', $this->queryBuilder->getDQL());
        self::assertEquals($foobar, $this->queryBuilder->getParameter('u_foobar')->getValue());
        self::assertEquals(ParameterType::STRING, $this->queryBuilder->getParameter('u_foobar')->getType());

        $user = $this->queryBuilder->getQuery()->getOneOrNullResult();
        self::assertEquals('goofy', $user->name);
    }
}
