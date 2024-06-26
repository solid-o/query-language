<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Tests\Processor\Doctrine\PhpCr;

use Doctrine\ODM\PHPCR\DocumentManagerInterface;
use Doctrine\ODM\PHPCR\Query\Builder\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Refugis\DoctrineExtra\ObjectIteratorInterface;
use Refugis\DoctrineExtra\ODM\PhpCr\DocumentIterator;
use Solido\DataMapper\DataMapperFactory;
use Solido\DataMapper\Exception\MappingErrorException;
use Solido\Pagination\PagerIterator;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Processor\FieldInterface;
use Solido\QueryLanguage\Processor\Doctrine\PhpCr\Processor;
use Solido\QueryLanguage\Tests\Doctrine\PhpCr\FixturesTrait;
use Solido\QueryLanguage\Tests\Fixtures\Document\FooBar;
use Solido\QueryLanguage\Tests\Fixtures\Document\User;
use Solido\QueryLanguage\Walker\Validation\ValidationWalker;
use Solido\QueryLanguage\Walker\Validation\ValidationWalkerInterface;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Validator\ValidatorBuilder;
use function assert;
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
            self::$documentManager->getRepository(User::class)->createQueryBuilder('u'),
            self::$documentManager,
            $this->dataMapperFactory
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

    public function testBuiltinLikeFieldWorks(): void
    {
        $this->processor->addField('name');
        $itr = $this->processor->processRequest(new Request(['name' => '$like(GOOFY)']));

        self::assertInstanceOf(ObjectIteratorInterface::class, $itr);
        $result = iterator_to_array($itr);

        self::assertCount(1, $result);
        self::assertInstanceOf(User::class, $result[0]);
        self::assertEquals('goofy', $result[0]->name);
    }

    public function testBuiltinOrFieldWorks(): void
    {
        $this->processor->addField('name');
        $itr = $this->processor->processRequest(new Request(['name' => '$or(goofy, barbar)']));

        self::assertInstanceOf(ObjectIteratorInterface::class, $itr);
        $result = iterator_to_array($itr);

        self::assertCount(2, $result);
        self::assertInstanceOf(User::class, $result[0]);
        self::assertEquals('goofy', $result[1]->name);
        self::assertInstanceOf(User::class, $result[1]);
        self::assertEquals('barbar', $result[0]->name);
    }

    public function testBuiltinNotFieldWorks(): void
    {
        $this->processor->addField('name');
        $itr = $this->processor->processRequest(new Request(['name' => '$not($or(goofy, barbar))']));

        self::assertInstanceOf(ObjectIteratorInterface::class, $itr);
        $result = iterator_to_array($itr);

        self::assertCount(5, $result);
    }

    public function testBuiltinOrderFieldWorks(): void
    {
        $this->processor = new Processor(
            self::$documentManager->getRepository(User::class)->createQueryBuilder('u'),
            self::$documentManager,
            $this->dataMapperFactory,
            ['order_field' => 'order']
        );

        $this->processor->addField('name');
        $itr = $this->processor->processRequest(new Request(['order' => '$order(name, desc)']));

        self::assertInstanceOf(ObjectIteratorInterface::class, $itr);
        $result = iterator_to_array($itr);

        self::assertCount(7, $result);
    }

    public function testBuiltinOrderPaginationFieldWorks(): void
    {
        $this->processor = new Processor(
            self::$documentManager->getRepository(User::class)->createQueryBuilder('u'),
            self::$documentManager,
            $this->dataMapperFactory,
            ['order_field' => 'order']
        );

        $this->processor->addField('name');
        $itr = $this->processor->processRequest(new Request(['order' => '$order(name, desc)']));
        assert($itr instanceof PagerIterator);

        self::assertInstanceOf(PagerIterator::class, $itr);
        $itr->setPageSize(2);
        $result = iterator_to_array($itr);

        self::assertCount(2, $result);
        self::assertEquals('=Zm9vYmFy_1_12r6se5', (string) $itr->getNextPageToken());

        $this->processor = new Processor(
            self::$documentManager->getRepository(User::class)->createQueryBuilder('u'),
            self::$documentManager,
            $this->dataMapperFactory,
            ['order_field' => 'order']
        );

        $this->processor->addField('name');
        $itr = $this->processor->processRequest(new Request(['order' => '$order(name, desc)', 'continue' => '=Zm9vYmFy_1_12r6se5']));
        assert($itr instanceof PagerIterator);

        self::assertInstanceOf(PagerIterator::class, $itr);
        $itr->setPageSize(2);
        $result = iterator_to_array($itr);

        self::assertCount(2, $result);
        self::assertEquals('=ZG9uYWxkIGR1Y2s=_1_epxv00', (string) $itr->getNextPageToken());
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

    public static function provideParamsForPageSize(): iterable
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
        $this->processor = new Processor(
            self::$documentManager->getRepository(User::class)->createQueryBuilder('u'),
            self::$documentManager,
            $this->dataMapperFactory,
            [
                'order_field' => 'order',
                'continuation_token' => true,
                'default_page_size' => 3,
            ]
        );

        $this->processor->addField('name');
        $itr = $this->processor->processRequest(new Request($params));

        self::assertInstanceOf(ObjectIteratorInterface::class, $itr);
        $result = iterator_to_array($itr);

        self::assertCount(3, $result);
    }

    public static function provideRangeHeaders(): iterable
    {
        yield [ 'bytes=0-', 7 ]; // Not supported

        yield [ 'units=0-0', 1 ];
        yield [ 'units=0-2', 3 ];
        yield [ 'after==YmF6_1_q7c73y', 4 ];
    }

    /**
     * @dataProvider provideRangeHeaders
     */
    public function testRangeHeader(string $rangeHeader, int $count): void
    {
        $this->processor = new Processor(
            self::$documentManager->getRepository(User::class)->createQueryBuilder('u'),
            self::$documentManager,
            $this->dataMapperFactory,
            ['continuation_token' => true],
        );

        $this->processor->addField('name');

        $request = new Request([]);
        $request->headers->set('X-Order', 'name; asc');
        $request->headers->set('Range', $rangeHeader);

        $itr = $this->processor->processRequest($request);

        self::assertInstanceOf(PagerIterator::class, $itr);
        self::assertCount($count, iterator_to_array($itr));
    }

    public function testOrderByDefaultFieldShouldThrowOnInvalidOptions(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->processor = new Processor(
            self::$documentManager->getRepository(User::class)->createQueryBuilder('u'),
            self::$documentManager,
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

    public static function provideParamsForDefaultOrder(): iterable
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
            self::$documentManager->getRepository(User::class)->createQueryBuilder('u'),
            self::$documentManager,
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

    public function testCustomFieldWorks(): void
    {
        $this->processor->addField('foobar', new class(self::$documentManager) implements FieldInterface {
            /** @var DocumentManagerInterface */
            private $documentManager;

            public function __construct(DocumentManagerInterface $entityManager)
            {
                $this->documentManager = $entityManager;
            }

            /**
             * @param QueryBuilder $queryBuilder
             */
            public function addCondition($queryBuilder, ExpressionInterface $expression): void
            {
                $queryBuilder->addJoinInner()
                    ->right()->document(FooBar::class, 'f')->end()
                    ->condition()->equi('u.foobar', 'f.uuid')->end()
                ->end();

                $queryBuilder->andWhere()
                    ->eq()->field('f.foobar')->literal($expression->getValue());
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

    public function testXOrderInRequestShouldWork(): void
    {
        $this->processor = new Processor(
            self::$documentManager->getRepository(User::class)->createQueryBuilder('u'),
            self::$documentManager,
            $this->dataMapperFactory,
            [
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
}
