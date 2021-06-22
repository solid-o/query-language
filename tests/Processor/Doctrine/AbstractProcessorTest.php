<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Tests\Processor\Doctrine;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Refugis\DoctrineExtra\ObjectIteratorInterface;
use Solido\QueryLanguage\Form\DTO\Query;
use Solido\QueryLanguage\Form\QueryType;
use Solido\QueryLanguage\Processor\Doctrine\AbstractProcessor;
use Solido\QueryLanguage\Processor\Doctrine\FieldInterface;
use Solido\QueryLanguage\Tests\Processor\DummyField;
use Solido\QueryLanguage\Walker\Validation\OrderWalker;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

class AbstractProcessorTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy|FormFactoryInterface */
    private ObjectProphecy $formFactory;

    protected function setUp(): void
    {
        $this->formFactory = $this->prophesize(FormFactoryInterface::class);
    }

    public function testCustomOrderValidationWalker(): void
    {
        $processor = new ConcreteProcessor($this->formFactory->reveal(), [
            'order_field' => 'order',
            'order_validation_walker' => $orderWalker = new OrderWalker(['test', 'foo']),
        ]);

        $this->formFactory->createNamed('', QueryType::class, Argument::type(Query::class), Argument::withEntry(
            'order_validation_walker',
            $orderWalker
        ))
            ->shouldBeCalled()
            ->willReturn($form = $this->prophesize(FormInterface::class));

        $form->handleRequest(Argument::any())->willReturn();
        $form->isSubmitted()->willReturn(true);
        $form->isValid()->willReturn(true);

        $processor->handleRequest(new Request(['order' => '$order(test, asc)']));
    }
}

class ConcreteProcessor extends AbstractProcessor
{
    protected function createField(string $fieldName): FieldInterface
    {
        return new DummyField();
    }

    /**
     * {@inheritdoc}
     */
    protected function getIdentifierFieldNames(): array
    {
        return ['id'];
    }

    /**
     * {@inheritdoc}
     */
    protected function buildIterator(object $queryBuilder, Query $result): ObjectIteratorInterface
    {
        // Do nothing
    }

    /**
     * {@inheritdoc}
     */
    public function handleRequest(Request $request)
    {
        return parent::handleRequest($request);
    }
}
