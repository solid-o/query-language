<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Tests\Processor\Doctrine\ORM;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Refugis\DoctrineExtra\ObjectIteratorInterface;
use Refugis\DoctrineExtra\ORM\EntityIterator;
use Solido\DataMapper\DataMapperFactory;
use Solido\DataMapper\Exception\MappingErrorException;
use Solido\Pagination\PagerIterator;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\OrderExpression;
use Solido\QueryLanguage\Processor\FieldInterface;
use Solido\QueryLanguage\Processor\Doctrine\ORM\Processor;
use Solido\QueryLanguage\Processor\OrderableFieldInterface;
use Solido\QueryLanguage\Tests\Doctrine\ORM\FixturesTrait;
use Solido\QueryLanguage\Tests\Fixtures\Entity\FooBar;
use Solido\QueryLanguage\Tests\Fixtures\Entity\User;
use Solido\QueryLanguage\Walker\Validation\ValidationWalker;
use Solido\QueryLanguage\Walker\Validation\ValidationWalkerInterface;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Validator\ValidatorBuilder;
use function iterator_to_array;

class ProcessorTest extends TestCase
{
    use FixturesTrait;

    private DataMapperFactory $dataMapperFactory;
    private Processor $processor;

    protected function setUp(): void
    {
        $formRegistry = new FormRegistry(
            [new ValidatorExtension((new ValidatorBuilder())->getValidator())],
            new ResolvedFormTypeFactory()
        );

        $this->dataMapperFactory = new DataMapperFactory();
        $this->dataMapperFactory->setFormRegistry($formRegistry);

        $this->processor = new Processor(
            self::$entityManager->getRepository(User::class)->createQueryBuilder('u'),
            $this->dataMapperFactory,
            [
                'order_field' => 'order',
                'continuation_token' => true,
            ],
        );
    }

    public function testBuiltinFieldWorks(): void
    {
        $this->processor->addField('name');
        $itr = $this->processor->processRequest(new Request(['name' => 'goofy']));

        self::assertInstanceOf(ObjectIteratorInterface::class, $itr);
        $result = iterator_to_array($itr);

        self::assertCount(1, $result);
        self::assertInstanceOf(User::class, $result[0]);
        self::assertEquals('goofy', $result[0]->name);
    }

    public function testRelationFieldWorks(): void
    {
        $this->processor->addField('foobar');
        $itr = $this->processor->processRequest(new Request(['foobar' => '$entry(foobar, foobar_donald duck)']));

        self::assertInstanceOf(ObjectIteratorInterface::class, $itr);
        $result = iterator_to_array($itr);

        self::assertCount(1, $result);
        self::assertInstanceOf(User::class, $result[0]);
        self::assertEquals('donald duck', $result[0]->name);
    }

    public function testRelationSimpleFieldWorks(): void
    {
        $this->processor->addField('foobar');
        $itr = $this->processor->processRequest(new Request(['foobar' => '12']));

        self::assertInstanceOf(ObjectIteratorInterface::class, $itr);
        iterator_to_array($itr);

        self::assertSame('SELECT u0_.id AS id_0, u0_.name AS name_1, u0_.nameLength AS nameLength_2, u0_.foobar_id AS foobar_id_3 FROM User u0_ WHERE 1 = 1 AND u0_.foobar_id = ? LIMIT 10', self::$queryLogs[0]['sql']);
        self::assertSame(12, self::$queryLogs[0]['params'][1]);

        $this->setUp();
        $this->processor->addField('foobar');
        $itr = $this->processor->processRequest(new Request(['foobar' => 'null']));

        self::assertInstanceOf(ObjectIteratorInterface::class, $itr);
        iterator_to_array($itr);
        self::assertSame('SELECT u0_.id AS id_0, u0_.name AS name_1, u0_.nameLength AS nameLength_2, u0_.foobar_id AS foobar_id_3 FROM User u0_ WHERE 1 = 1 AND u0_.foobar_id IS NULL LIMIT 10', self::$queryLogs[1]['sql']);
    }

    public function testManyToManyRelationFieldWorks(): void
    {
        $this->processor->addField('groups');
        $itr = $this->processor->processRequest(new Request(['groups' => '1']));

        self::assertInstanceOf(ObjectIteratorInterface::class, $itr);
        iterator_to_array($itr);

        self::assertSame('SELECT DISTINCT u0_.id AS id_0, u0_.name AS name_1, u0_.nameLength AS nameLength_2, u0_.foobar_id AS foobar_id_3 FROM User u0_ INNER JOIN user_group u2_ ON u0_.id = u2_.user_id INNER JOIN u_group u1_ ON u1_.id = u2_.group_id AND (u1_.id = ?) WHERE 1 = 1 LIMIT 10', self::$queryLogs[0]['sql']);
        self::assertSame(1, self::$queryLogs[0]['params'][1]);
    }

    public function testFieldWithFieldInRelatedEntityWorks(): void
    {
        $this->processor->addField('foobar', ['field_name' => 'foobar.foobar']);
        $itr = $this->processor->processRequest(new Request(['foobar' => 'foobar_donald duck']));

        self::assertInstanceOf(ObjectIteratorInterface::class, $itr);
        $result = iterator_to_array($itr);

        self::assertCount(1, $result);
        self::assertInstanceOf(User::class, $result[0]);
        self::assertEquals('donald duck', $result[0]->name);
    }

    public function provideParamsForPageSize(): iterable
    {
        yield [[]];
        yield [['order' => '$order(name)']];
        yield [['order' => '$order(name)', 'continue' => '=YmF6_1_10tf9ny']];
    }

    /**
     * @dataProvider provideParamsForPageSize
     */
    public function testPageSizeOptionShouldWork(array $params): void
    {
        $this->processor->addField('name');
        $this->processor->setDefaultPageSize(3);
        $itr = $this->processor->processRequest(new Request($params));

        self::assertInstanceOf(ObjectIteratorInterface::class, $itr);
        $result = iterator_to_array($itr);

        self::assertCount(3, $result);
    }

    public function testOrderByDefaultFieldShouldThrowOnInvalidOptions(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->processor = new Processor(
            self::$entityManager->getRepository(User::class)->createQueryBuilder('u'),
            $this->dataMapperFactory,
            [
                'default_order' => '$eq(name)',
                'order_field' => 'order',
                'continuation_token' => true,
            ],
        );

        $this->processor->addField('name');
        $this->processor->setDefaultPageSize(3);
        $this->processor->processRequest(new Request([]));
    }

    public function provideParamsForDefaultOrder(): iterable
    {
        yield [true, '$order(name)'];
        yield [true, 'name'];
        yield [true, 'name, desc'];
        yield [false, '$order(nonexistent, asc)'];
    }

    /**
     * @dataProvider provideParamsForDefaultOrder
     */
    public function testOrderByDefaultFieldShouldWork(bool $valid, string $defaultOrder): void
    {
        $this->processor = new Processor(
            self::$entityManager->getRepository(User::class)->createQueryBuilder('u'),
            $this->dataMapperFactory,
            [
                'default_order' => $defaultOrder,
                'order_field' => 'order',
                'continuation_token' => true,
            ],
        );

        if (! $valid) {
            $this->expectException(MappingErrorException::class);
        }

        $this->processor->addField('name');
        $this->processor->setDefaultPageSize(3);
        $itr = $this->processor->processRequest(new Request([]));

        self::assertInstanceOf(PagerIterator::class, $itr);
    }

    public function provideRangeHeaders(): iterable
    {
        yield [ 'bytes=0-', 7 ]; // Not supported

        yield [ 'units=0-0', 1 ];
        yield [ 'units=0-2', 3 ];
        yield [ 'after==YmF6_1_10tf9ny', 4 ];
    }

    /**
     * @dataProvider provideRangeHeaders
     */
    public function testRangeHeader(string $rangeHeader, int $count): void
    {
        $this->processor = new Processor(
            self::$entityManager->getRepository(User::class)->createQueryBuilder('u'),
            $this->dataMapperFactory,
            [ 'continuation_token' => true ],
        );

        $this->processor->addField('name');

        $request = new Request([]);
        $request->headers->set('X-Order', 'name; asc');
        $request->headers->set('Range', $rangeHeader);

        $itr = $this->processor->processRequest($request);

        self::assertInstanceOf(PagerIterator::class, $itr);
        self::assertCount($count, iterator_to_array($itr));
    }

    public function testCustomFieldWorks(): void
    {
        $this->processor->addField('foobar', new class(self::$entityManager) implements FieldInterface {
            /** @var EntityManagerInterface */
            private $entityManager;

            public function __construct(EntityManagerInterface $entityManager)
            {
                $this->entityManager = $entityManager;
            }

            public function addCondition($queryBuilder, ExpressionInterface $expression): void
            {
                $foobar = $this->entityManager->getRepository(FooBar::class)
                    ->findOneBy(['foobar' => $expression->getValue()]);

                $queryBuilder->andWhere('u.foobar = :foobar')
                    ->setParameter('foobar', $foobar);
            }

            public function getValidationWalker(): ValidationWalkerInterface
            {
                return new ValidationWalker();
            }
        });
        $itr = $this->processor->processRequest(new Request(['foobar' => 'foobar_barbar']));

        self::assertInstanceOf(ObjectIteratorInterface::class, $itr);
        $result = iterator_to_array($itr);

        self::assertCount(1, $result);
        self::assertInstanceOf(User::class, $result[0]);
        self::assertEquals('barbar', $result[0]->name);
    }

    public function testGetOrderCanReturnEmptyArray(): void
    {
        $this->processor->addField('foobar', new class(self::$entityManager) implements OrderableFieldInterface {
            /** @var EntityManagerInterface */
            private $entityManager;

            public function __construct(EntityManagerInterface $entityManager)
            {
                $this->entityManager = $entityManager;
            }

            public function addCondition($queryBuilder, ExpressionInterface $expression): void
            {
                $foobar = $this->entityManager->getRepository(FooBar::class)
                    ->findOneBy(['foobar' => $expression->getValue()]);

                $queryBuilder->andWhere('u.foobar = :foobar')
                    ->setParameter('foobar', $foobar);
            }

            public function getOrder(object $queryBuilder, OrderExpression $orderExpression): array
            {
                return ['foobar', 'asc'];
            }

            public function getValidationWalker(): ValidationWalkerInterface
            {
                return new ValidationWalker();
            }
        });
        $request = new Request(['foobar' => 'foobar_barbar']);
        $request->headers->set('X-Order', 'foobar; asc');
        $itr = $this->processor->processRequest($request);

        self::assertInstanceOf(ObjectIteratorInterface::class, $itr);
        $result = iterator_to_array($itr);

        self::assertCount(1, $result);
        self::assertInstanceOf(User::class, $result[0]);
        self::assertEquals('barbar', $result[0]->name);
    }

    public function testXOrderInRequestShouldWork(): void
    {
        $this->processor = new Processor(
            self::$entityManager->getRepository(User::class)->createQueryBuilder('u'),
            $this->dataMapperFactory,
            [
                'order_field' => 'order',
                'continuation_token' => true,
            ],
        );

        $this->processor->addField('name');
        $this->processor->setDefaultPageSize(3);

        $request = new Request([]);
        $request->headers->set('X-Order', 'name; desc');
        $itr = $this->processor->processRequest($request);

        self::assertInstanceOf(PagerIterator::class, $itr);
    }

    public function testCouldBeOrderedByAssociationField(): void
    {
        $this->processor = new Processor(
            self::$entityManager->getRepository(User::class)->createQueryBuilder('u'),
            $this->dataMapperFactory,
            [
                'continuation_token' => true,
            ],
        );

        $this->processor->addField('name');
        $this->processor->addField('foo', [ 'field_name' => 'foobar.foobar' ]);

        $request = new Request([]);
        $request->headers->set('X-Order', 'foo; desc');
        $itr = $this->processor->processRequest($request);

        self::assertInstanceOf(PagerIterator::class, $itr);
        $result = iterator_to_array($itr);

        self::assertCount(7, $result);
        self::assertInstanceOf(User::class, $result[0]);
        self::assertEquals('goofy', $result[0]->name);

        $request->headers->set('X-Order', 'foo; asc');
        $itr = $this->processor->processRequest($request);
        $result = iterator_to_array($itr);
        self::assertEquals('bar', $result[0]->name);
    }
}
