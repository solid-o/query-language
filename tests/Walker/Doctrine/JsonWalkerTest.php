<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Tests\Walker\Doctrine;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Solido\QueryLanguage\Expression\Literal\LiteralExpression;
use Solido\QueryLanguage\Tests\Doctrine\ORM\FixturesTrait;
use Solido\QueryLanguage\Tests\Fixtures\Entity as QueryLanguageFixtures;
use Solido\QueryLanguage\Walker\Doctrine\JsonWalker;

class JsonWalkerTest extends TestCase
{
    use FixturesTrait;

    private QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $userRepository = self::$entityManager->getRepository(QueryLanguageFixtures\User::class);
        $this->queryBuilder = $userRepository->createQueryBuilder('u');
    }

    public function testShouldBuildStringEqualsComparison(): void
    {
        $walker = new JsonWalker($this->queryBuilder, 'u.name');
        $this->queryBuilder->andWhere(
            $walker->walkComparison('=', LiteralExpression::create('{"foo":"bar"}'))
        );

        self::assertSame("SELECT u FROM Solido\QueryLanguage\Tests\Fixtures\Entity\User u WHERE CONCAT('', u.name) = :u_name", $this->queryBuilder->getDQL());
        self::assertSame('{"foo":"bar"}', $this->queryBuilder->getParameter('u_name')->getValue());
        self::assertSame(Types::STRING, $this->queryBuilder->getParameter('u_name')->getType());
    }

    public function testShouldThrowOnUnknownStrategy(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new JsonWalker($this->queryBuilder, 'u.name', 'foo');
    }

    public function testShouldBuildContainsComparison(): void
    {
        $walker = new JsonWalker($this->queryBuilder, 'u.name', JsonWalker::STRATEGY_CONTAINS);
        $this->queryBuilder->andWhere(
            $walker->walkComparison('=', LiteralExpression::create('foo'))
        );

        self::assertSame("SELECT u FROM Solido\QueryLanguage\Tests\Fixtures\Entity\User u WHERE CONCAT('', u.name) LIKE :u_name", $this->queryBuilder->getDQL());
        self::assertSame('%foo%', $this->queryBuilder->getParameter('u_name')->getValue());
        self::assertSame(Types::STRING, $this->queryBuilder->getParameter('u_name')->getType());
    }
}
