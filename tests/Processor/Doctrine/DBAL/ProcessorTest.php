<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Tests\Processor\Doctrine\DBAL;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Refugis\DoctrineExtra\DBAL\RowIterator;
use Refugis\DoctrineExtra\ObjectIteratorInterface;
use Solido\DataMapper\DataMapperFactory;
use Solido\DataMapper\Exception\MappingErrorException;
use Solido\Pagination\PagerIterator;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Processor\FieldInterface;
use Solido\QueryLanguage\Processor\Doctrine\DBAL\Processor;
use Solido\QueryLanguage\Tests\Doctrine\ORM\FixturesTrait;
use Solido\QueryLanguage\Tests\Fixtures\Entity\FooBar;
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
use function iterator_to_array;

class ProcessorTest extends TestCase
{
    use FixturesTrait;

    private Processor $processor;
    private DataMapperFactory $dataMapperFactory;

    protected function setUp(): void
    {
        $formRegistry = new FormRegistry(
            [new ValidatorExtension((new ValidatorBuilder())->getValidator())],
            new ResolvedFormTypeFactory()
        );

        $this->dataMapperFactory = new DataMapperFactory();
        $this->dataMapperFactory->setFormRegistry($formRegistry);

        $queryBuilder = self::$entityManager->getConnection()->createQueryBuilder();
        $queryBuilder
            ->select('id', 'name', 'nameLength')
            ->from('user', 'u');

        $this->processor = new Processor(
            $queryBuilder,
            $this->dataMapperFactory,
            [
                'order_field' => 'order',
                'continuation_token' => true,
                'identifiers' => ['id'],
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
        self::assertEquals('goofy', $result[0]['name']);
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

    public function testPageTokenInRangeHeader(): void
    {
        $this->processor->addField('name');
        $this->processor->setDefaultPageSize(3);

        $request = new Request();
        $request->headers->set('X-Order', 'name; asc');
        $request->headers->set('Range', 'after==YmF6_1_10tf9ny');
        $itr = $this->processor->processRequest($request);

        self::assertInstanceOf(ObjectIteratorInterface::class, $itr);
        $result = iterator_to_array($itr);

        self::assertCount(3, $result);
        self::assertEquals('=Zm9vYmFy_1_ugzi57', (string) $itr->getNextPageToken());
    }

    public function testOrderByDefaultFieldShouldThrowOnInvalidOptions(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $queryBuilder = self::$entityManager->getConnection()->createQueryBuilder();
        $queryBuilder
            ->select('id', 'name', 'nameLength')
            ->from('user', 'u');

        $this->processor = new Processor(
            $queryBuilder,
            $this->dataMapperFactory,
            [
                'default_order' => '$eq(name)',
                'order_field' => 'order',
                'continuation_token' => true,
                'identifiers' => ['id'],
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
        $queryBuilder = self::$entityManager->getConnection()->createQueryBuilder();
        $queryBuilder
            ->select('id', 'name', 'nameLength')
            ->from('user', 'u');

        $this->processor = new Processor(
            $queryBuilder,
            $this->dataMapperFactory,
            [
                'default_order' => $defaultOrder,
                'order_field' => 'order',
                'continuation_token' => true,
                'identifiers' => ['id'],
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
        $this->processor->addField('foobar', new class(self::$entityManager) implements FieldInterface {
            private EntityManagerInterface $entityManager;

            public function __construct(EntityManagerInterface $entityManager)
            {
                $this->entityManager = $entityManager;
            }

            public function addCondition($queryBuilder, ExpressionInterface $expression): void
            {
                $foobar = $this->entityManager->getRepository(FooBar::class)
                    ->findOneBy(['foobar' => $expression->getValue()]);

                $queryBuilder->andWhere('u.foobar_id = :foobar')
                    ->setParameter('foobar', $foobar->id);
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
        self::assertEquals('barbar', $result[0]['name']);
    }


    public function testXOrderInRequestShouldWork(): void
    {
        $queryBuilder = self::$entityManager->getConnection()->createQueryBuilder();
        $queryBuilder
            ->select('id', 'name', 'nameLength')
            ->from('user', 'u');

        $this->processor = new Processor(
            $queryBuilder,
            $this->dataMapperFactory,
            [
                'continuation_token' => true,
                'identifiers' => ['id'],
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
