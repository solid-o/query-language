<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Tests\Walker\DBAL;

use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Solido\QueryLanguage\Expression\Literal\LiteralExpression;
use Solido\QueryLanguage\Tests\Doctrine\ORM\FixturesTrait;
use Solido\QueryLanguage\Walker\DBAL\SqlWalker;

class SqlWalkerTest extends TestCase
{
    use FixturesTrait;

    private SqlWalker $walker;
    private QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $connection = self::$entityManager->getConnection();
        $this->queryBuilder = $connection->createQueryBuilder()
            ->select('id', 'name')
            ->from('user', 'u');

        $this->walker = new SqlWalker($this->queryBuilder, 'u.name');
    }

    public function testShouldBuildEqualComparison(): void
    {
        $this->queryBuilder->andWhere(
            $this->walker->walkComparison('=', LiteralExpression::create('foo'))
        );

        self::assertEquals('SELECT id, name FROM user u WHERE "u"."name" = :u_name', $this->queryBuilder->getSQL());
        self::assertEquals('foo', $this->queryBuilder->getParameter('u_name'));
    }
}
