<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Tests\Processor\Doctrine\ORM;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Refugis\DoctrineExtra\ObjectIteratorInterface;
use Refugis\DoctrineExtra\ORM\EntityIterator;
use Solido\Common\Form\AutoSubmitRequestHandler;
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
use Symfony\Component\Form\Extension\HttpFoundation\Type\FormTypeHttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Validator\ValidatorBuilder;
use function iterator_to_array;

class ProcessorTest extends TestCase
{
    use FixturesTrait;

    private Processor $processor;

    protected function setUp(): void
    {
        $formFactory = (new FormFactoryBuilder(true))
            ->addExtension(new ValidatorExtension((new ValidatorBuilder())->getValidator()))
            ->getFormFactory();

        $this->processor = new Processor(
            self::$entityManager->getRepository(User::class)->createQueryBuilder('u'),
            $formFactory,
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

        $formFactory = (new FormFactoryBuilder(true))
            ->addExtension(new ValidatorExtension((new ValidatorBuilder())->getValidator()))
            ->getFormFactory();

        $this->processor = new Processor(
            self::$entityManager->getRepository(User::class)->createQueryBuilder('u'),
            $formFactory,
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
        $formFactory = (new FormFactoryBuilder(true))
            ->addExtension(new ValidatorExtension((new ValidatorBuilder())->getValidator()))
            ->getFormFactory();

        $this->processor = new Processor(
            self::$entityManager->getRepository(User::class)->createQueryBuilder('u'),
            $formFactory,
            [
                'default_order' => $defaultOrder,
                'order_field' => 'order',
                'continuation_token' => true,
            ],
        );

        $this->processor->addField('name');
        $this->processor->setDefaultPageSize(3);
        $itr = $this->processor->processRequest(new Request([]));

        if (! $valid) {
            self::assertInstanceOf(FormInterface::class, $itr);
        } else {
            self::assertInstanceOf(PagerIterator::class, $itr);
        }
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
        $formFactory = (new FormFactoryBuilder(true))
            ->addExtension(new ValidatorExtension((new ValidatorBuilder())->getValidator()))
            ->getFormFactory();

        $this->processor = new Processor(
            self::$entityManager->getRepository(User::class)->createQueryBuilder('u'),
            $formFactory,
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

    public function testContinuationTokenCouldBeDisabled(): void
    {
        $formFactory = (new FormFactoryBuilder(true))
            ->addExtension(new ValidatorExtension((new ValidatorBuilder())->getValidator()))
            ->getFormFactory();

        $this->processor = new Processor(
            self::$entityManager->getRepository(User::class)->createQueryBuilder('u'),
            $formFactory,
            [
                'default_order' => 'name, desc',
                'order_field' => 'order',
                'continuation_token' => false,
            ],
        );

        $this->processor->addField('name');
        $this->processor->setDefaultPageSize(3);
        $itr = $this->processor->processRequest(new Request([]));

        self::assertInstanceOf(EntityIterator::class, $itr);
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

            public function getValidationWalker()
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
                return [];
            }

            public function getValidationWalker()
            {
                return new ValidationWalker();
            }
        });
        $request = new Request(['foobar' => 'foobar_barbar']);
        $request->headers->set('X-Order', 'foobar, asc');
        $itr = $this->processor->processRequest($request);

        self::assertInstanceOf(ObjectIteratorInterface::class, $itr);
        $result = iterator_to_array($itr);

        self::assertCount(1, $result);
        self::assertInstanceOf(User::class, $result[0]);
        self::assertEquals('barbar', $result[0]->name);
    }

    public function testXOrderInRequestShouldWork(): void
    {
        $formFactory = (new FormFactoryBuilder(true))
            ->addExtension(new ValidatorExtension((new ValidatorBuilder())->getValidator()))
            ->getFormFactory();

        $this->processor = new Processor(
            self::$entityManager->getRepository(User::class)->createQueryBuilder('u'),
            $formFactory,
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
}
