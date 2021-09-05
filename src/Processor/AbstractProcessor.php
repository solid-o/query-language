<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Processor;

use Iterator;
use Solido\Common\AdapterFactory;
use Solido\Common\AdapterFactoryInterface;
use Solido\Pagination\PageToken;
use Solido\QueryLanguage\Expression\OrderExpression;
use Solido\QueryLanguage\Form\DTO\Query;
use Solido\QueryLanguage\Form\QueryType;
use Solido\QueryLanguage\Grammar\Grammar;
use Solido\QueryLanguage\Walker\Validation\ValidationWalkerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_filter;
use function array_keys;
use function Safe\preg_match;
use function strpos;

abstract class AbstractProcessor
{
    /** @var FieldInterface[] */
    protected array $fields;

    /** @var mixed[] */
    protected array $options;
    private FormFactoryInterface $formFactory;
    private AdapterFactoryInterface $adapterFactory;

    /**
     * @param mixed[] $options
     */
    public function __construct(FormFactoryInterface $formFactory, array $options = [])
    {
        $this->options = $this->resolveOptions($options);
        $this->fields = [];
        $this->formFactory = $formFactory;
        $this->adapterFactory = new AdapterFactory();
    }

    public function setAdapterFactory(AdapterFactoryInterface $adapterFactory): void
    {
        $this->adapterFactory = $adapterFactory;
    }

    /**
     * Sets the default page size (will be used if limit is disabled or not passed by the user).
     */
    public function setDefaultPageSize(?int $size): void
    {
        $this->options['default_page_size'] = $size;
    }

    /**
     * Adds a field to this list processor.
     *
     * @param array<null|string|callable>|FieldInterface $options
     *
     * @return $this
     */
    abstract public function addField(string $name, $options = []): self;

    /**
     * Binds and validates the request to the internal Query object.
     *
     * @return Query|FormInterface
     */
    protected function handleRequest(object $request)
    {
        $defaultOrder = $this->options['default_order'];
        $adapter = $this->adapterFactory->createRequestAdapter($request);

        $orderHeader = $adapter->getHeader('X-Order')[0] ?? null;
        if ($orderHeader) {
            $defaultOrder = OrderExpression::fromHeader($orderHeader);
        }

        $options = [
            'limit_field' => $this->options['limit_field'],
            'skip_field' => $this->options['skip_field'],
            'order_field' => $this->options['order_field'],
            'default_order' => $defaultOrder,
            'continuation_token_field' => $this->options['continuation_token']['field'] ?? null,
            'fields' => $this->fields,
            'orderable_fields' => array_keys(array_filter($this->fields, static fn (FieldInterface $field) => $field instanceof OrderableFieldInterface)),
        ];

        if ($this->options['order_validation_walker'] !== null) {
            $options['order_validation_walker'] = $this->options['order_validation_walker'];
        }

        $form = $this->formFactory->createNamed('', QueryType::class, $dto = new Query(), $options);
        $form->handleRequest($request);
        if ($form->isSubmitted() && ! $form->isValid()) {
            return $form;
        }

        $range = $adapter->getHeader('Range')[0] ?? null;
        if (
            $this->options['range-header'] && ! empty($range) &&
            preg_match('/^(units=(?P<start>\d+)-(?P<end>\d+)|after=(?P<token>[^\s,.]+))$/', $range, $matches)
        ) {
            if (
                $dto->skip === null && $dto->limit === null &&
                isset($matches['start'], $matches['end']) &&
                $matches['start'] !== '' && $matches['end'] !== ''
            ) {
                $dto->skip = (int) $matches['start'];
                $dto->limit = ((int) $matches['end']) - ((int) $matches['start']) + 1;
            } elseif ($dto->pageToken === null && isset($matches['token']) && PageToken::isValid($matches['token'])) {
                $dto->pageToken = PageToken::parse($matches['token']);
            }
        }

        return $dto;
    }

    /**
     * Gets the identifier field names.
     *
     * @return string[]
     */
    abstract protected function getIdentifierFieldNames(): array;

    /**
     * Builds an ObjectIterator from the given query builder.
     * Allow to make some final/general customization of the query, before firing it to the database engine.
     *
     * @return Iterator<object>
     */
    abstract protected function buildIterator(object $queryBuilder, Query $result): Iterator;

    /**
     * Parses the ordering expression for continuation token.
     *
     * @return array<string, string>
     * @phpstan-return array<string, 'asc'|'desc'>
     */
    protected function parseOrderings(object $queryBuilder, OrderExpression $ordering): array
    {
        $field = $this->fields[$ordering->getField()];
        if (! ($field instanceof OrderableFieldInterface)) {
            return [];
        }

        $order = $field->getOrder($queryBuilder, $ordering);
        if (empty($order)) {
            return [];
        }

        $checksumField = $this->getOrderChecksumFieldName();

        return [
            $order[0] => $order[1],
            $checksumField => 'asc',
        ];
    }

    /**
     * Gets the checksum field name base on options and added fields.
     */
    protected function getOrderChecksumFieldName(): string
    {
        $checksumField = $this->getIdentifierFieldNames()[0];
        if (isset($this->options['continuation_token']['checksum_field'])) {
            $checksumField = $this->options['continuation_token']['checksum_field'];
            $checksumField = $this->fields[$checksumField]->fieldName;
        }

        return $checksumField;
    }

    /**
     * Allow to deeply configure the options resolver.
     */
    protected function configureOptions(OptionsResolver $resolver): void
    {
        // Do nothing.
    }

    /**
     * Resolves options for this processor.
     *
     * @param mixed[] $options
     *
     * @return mixed[]
     */
    private function resolveOptions(array $options): array
    {
        $resolver = new OptionsResolver();

        foreach (['skip_field', 'limit_field', 'default_order'] as $field) {
            $resolver
                ->setDefault($field, null)
                ->setAllowedTypes($field, ['null', 'string']);
        }

        $resolver
            ->setDefault('range-header', true)
            ->setAllowedTypes('range-header', ['bool'])
            ->setDefault('order_field', '__x_order_header')
            ->setAllowedTypes('order_field', ['null', 'string'])
            ->setDefault('default_page_size', null)
            ->setAllowedTypes('default_page_size', ['null', 'int'])
            ->setDefault('max_page_size', null)
            ->setAllowedTypes('max_page_size', ['null', 'int'])
            ->setDefault('order_validation_walker', null)
            ->setAllowedTypes('order_validation_walker', ['null', ValidationWalkerInterface::class])
            ->setDefault('continuation_token', [
                'field' => 'continue',
                'checksum_field' => null,
            ])
            ->setAllowedTypes('continuation_token', ['bool', 'array'])
            ->setNormalizer('continuation_token', static function (Options $options, $value) {
                if ($value === true) {
                    return [
                        'field' => 'continue',
                        'checksum_field' => null,
                    ];
                }

                if ($value !== false && ! isset($value['field'])) {
                    throw new InvalidOptionsException('Continuation token field must be set');
                }

                return $value;
            })
            ->setNormalizer('default_order', static function (Options $options, $value): ?OrderExpression {
                if (empty($value)) {
                    return null;
                }

                if ($value instanceof OrderExpression) {
                    return $value;
                }

                if (strpos($value, '$') === false) {
                    $value = '$order(' . $value . ')';
                }

                $grammar = Grammar::getInstance();
                $expression = $grammar->parse($value);

                if (! $expression instanceof OrderExpression) {
                    throw new InvalidOptionsException('Invalid default order specified');
                }

                return $expression;
            });

        $this->configureOptions($resolver);

        return $resolver->resolve($options);
    }
}
