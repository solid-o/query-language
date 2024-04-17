<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Tests\Grammar;

use PHPUnit\Framework\TestCase;
use Solido\QueryLanguage\Exception\SyntaxError;
use Solido\QueryLanguage\Expression;
use Solido\QueryLanguage\Grammar\Grammar;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

class GrammarTest extends TestCase
{
    use VarDumperTestTrait;

    private Grammar $grammar;

    protected function setUp(): void
    {
        $this->grammar = new Grammar();
    }

    /**
     * @dataProvider provideExpressions
     */
    public function testParse(string $query, Expression\ExpressionInterface $expected): void
    {
        $this->assertDumpEquals($this->getDump($expected), $this->grammar->parse($query));
    }

    /**
     * @dataProvider provideExpressions
     */
    public function testParseWithCache(string $query, Expression\ExpressionInterface $expected): void
    {
        $this->grammar = new Grammar(new ArrayAdapter(3600));
        $this->assertDumpEquals($this->getDump($expected), $this->grammar->parse($query));
        $this->assertDumpEquals($this->getDump($expected), $this->grammar->parse($query));
    }

    public static function provideExpressions(): iterable
    {
        yield ['$all', new Expression\AllExpression()];
        yield [
            '$not(2)',
            Expression\Logical\NotExpression::create(
                new Expression\Comparison\EqualExpression(Expression\Literal\LiteralExpression::create('2'))
            ),
        ];

        yield ['$eq(2)', new Expression\Comparison\EqualExpression(Expression\Literal\LiteralExpression::create('2'))];
        yield ['$eq(null)', new Expression\Comparison\EqualExpression(Expression\Literal\LiteralExpression::create('null'))];
        yield [
            '$neq(2)',
            Expression\Logical\NotExpression::create(
                new Expression\Comparison\EqualExpression(Expression\Literal\LiteralExpression::create('2'))
            ),
        ];
        yield [
            '$neq(null)',
            Expression\Logical\NotExpression::create(
                new Expression\Comparison\EqualExpression(Expression\Literal\LiteralExpression::create('null'))
            )
        ];

        yield ['$like(foobar)', new Expression\Comparison\LikeExpression(Expression\Literal\LiteralExpression::create('foobar'))];
        yield ['$lt(2)', new Expression\Comparison\LessThanExpression(Expression\Literal\LiteralExpression::create('2'))];
        yield ['$lte(2)', new Expression\Comparison\LessThanOrEqualExpression(Expression\Literal\LiteralExpression::create('2'))];
        yield ['$gt(2)', new Expression\Comparison\GreaterThanExpression(Expression\Literal\LiteralExpression::create('2'))];
        yield ['$gte(2)', new Expression\Comparison\GreaterThanOrEqualExpression(Expression\Literal\LiteralExpression::create('2'))];

        yield [
            '$and(2, $like(foobar))',
            Expression\Logical\AndExpression::create([
                new Expression\Comparison\EqualExpression(Expression\Literal\LiteralExpression::create('2')),
                new Expression\Comparison\LikeExpression(Expression\Literal\LiteralExpression::create('foobar')),
            ]),
        ];

        yield [
            '$or(2, $like(foobar))',
            Expression\Logical\OrExpression::create([
                new Expression\Comparison\EqualExpression(Expression\Literal\LiteralExpression::create('2')),
                new Expression\Comparison\LikeExpression(Expression\Literal\LiteralExpression::create('foobar')),
            ]),
        ];

        yield [
            '$in(2, 4)',
            Expression\Logical\OrExpression::create([
                new Expression\Comparison\EqualExpression(Expression\Literal\LiteralExpression::create('2')),
                new Expression\Comparison\EqualExpression(Expression\Literal\LiteralExpression::create('4')),
            ]),
        ];

        yield [
            '$range(2, 4)',
            Expression\Logical\AndExpression::create([
                new Expression\Comparison\GreaterThanOrEqualExpression(Expression\Literal\LiteralExpression::create('2')),
                new Expression\Comparison\LessThanOrEqualExpression(Expression\Literal\LiteralExpression::create('4')),
            ]),
        ];

        yield [
            '$entry(prop, null)',
            Expression\EntryExpression::create(
                Expression\Literal\LiteralExpression::create('prop'),
                new Expression\Comparison\EqualExpression(Expression\Literal\LiteralExpression::create('null'))
            ),
        ];

        yield [
            '$entry(prop, $and($gte(2), $like(bar\$like\(bar\))))',
            Expression\EntryExpression::create(
                Expression\Literal\LiteralExpression::create('prop'),
                Expression\Logical\AndExpression::create([
                    new Expression\Comparison\GreaterThanOrEqualExpression(Expression\Literal\LiteralExpression::create('2')),
                    new Expression\Comparison\LikeExpression(Expression\Literal\LiteralExpression::create('bar$like(bar)')),
                ])
            ),
        ];

        yield [
            '$gte(2019-10-14T02:00:00.000Z)',
            new Expression\Comparison\GreaterThanOrEqualExpression(
                Expression\Literal\LiteralExpression::create('2019-10-14T02:00:00.000Z')
            ),
        ];

        // Not expressions
        yield [
            '$and($not(5), 8)',
            Expression\Logical\AndExpression::create([
                Expression\Logical\NotExpression::create(
                    new Expression\Comparison\EqualExpression(Expression\Literal\LiteralExpression::create('5'))
                ),
                new Expression\Comparison\EqualExpression(Expression\Literal\LiteralExpression::create('8')),
            ]),
        ];

        yield [
            '$and(5, $not(8))',
            Expression\Logical\AndExpression::create([
                new Expression\Comparison\EqualExpression(Expression\Literal\LiteralExpression::create('5')),
                Expression\Logical\NotExpression::create(
                    new Expression\Comparison\EqualExpression(Expression\Literal\LiteralExpression::create('8'))
                ),
            ]),
        ];
    }

    /**
     * @dataProvider provideInvalidExpressions
     */
    public function testParseShouldThrowOnInvalidSyntax(string $query): void
    {
        $this->expectException(SyntaxError::class);
        $this->grammar->parse($query);
    }

    public static function provideInvalidExpressions(): iterable
    {
        yield ['$eq'];
        yield ['$neq'];
        yield ['$like($fofo)'];
        yield ['$nonex(test)'];
    }
}
