<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Processor\Doctrine;

use InvalidArgumentException;
use Solido\QueryLanguage\Expression\OrderExpression;
use Solido\QueryLanguage\Form\DTO\Query;
use Solido\QueryLanguage\Form\QueryType;
use Solido\QueryLanguage\Grammar\Grammar;
use Solido\QueryLanguage\Processor\Doctrine\FieldInterface as DoctrineFieldInterface;
use Solido\QueryLanguage\Processor\FieldInterface;
use Solido\QueryLanguage\Processor\OrderableFieldInterface;
use Solido\QueryLanguage\Walker\Validation\ValidationWalkerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use function array_filter;
use function array_keys;
use function Safe\sprintf;
use function strpos;

abstract class AbstractProcessor
{
    /** @var FieldInterface[] */
    protected array $fields;

    /** @var mixed[] */
    protected array $options;
    private FormFactoryInterface $formFactory;

    /**
     * @param mixed[] $options
     */
    public function __construct(FormFactoryInterface $formFactory, array $options = [])
    {
        $this->options = $this->resolveOptions($options);
        $this->fields = [];
        $this->formFactory = $formFactory;
    }

    /**
     * Sets the default page size (will be used if limit is disabled or not passed by the user).
     */
    public function setDefaultPageSize(?int $size): void
    {
        $this->options['default_page_size'] = $size;
    }

    /**
     * Adds a column to this list processor.
     *
     * @param array<null|string|callable>|FieldInterface $options
     *
     * @return $this
     */
    public function addField(string $name, $options = []): self
    {
        if ($options instanceof FieldInterface) {
            $this->fields[$name] = $options;

            return $this;
        }

        $resolver = new OptionsResolver();
        $options = $resolver
            ->setDefaults([
                'field_name' => $name,
                'walker' => null,
                'validation_walker' => null,
            ])
            ->setAllowedTypes('field_name', 'string')
            ->setAllowedTypes('walker', ['null', 'string', 'callable'])
            ->setAllowedTypes('validation_walker', ['null', 'string', 'callable'])
            ->resolve($options);

        $column = $this->createField($options['field_name']);

        if ($options['walker'] !== null) {
            $column->customWalker = $options['walker'];
        }

        if ($options['validation_walker'] !== null) {
            $column->validationWalker = $options['validation_walker'];
        }

        $this->fields[$name] = $column;

        return $this;
    }

    /**
     * Binds and validates the request to the internal Query object.
     *
     * @return Query|FormInterface
     */
    protected function handleRequest(Request $request)
    {
        $options = [
            'limit_field' => $this->options['limit_field'],
            'skip_field' => $this->options['skip_field'],
            'order_field' => $this->options['order_field'],
            'default_order' => $this->options['default_order'],
            'continuation_token_field' => $this->options['continuation_token']['field'] ?? null,
            'columns' => $this->fields,
            'orderable_columns' => array_keys(array_filter($this->fields, static function (FieldInterface $column): bool {
                return $column instanceof OrderableFieldInterface;
            })),
        ];

        if ($this->options['order_validation_walker'] !== null) {
            $options['order_validation_walker'] = $this->options['order_validation_walker'];
        }

        $form = $this->formFactory->createNamed('', QueryType::class, $dto = new Query(), $options);
        $form->handleRequest($request);
        if ($form->isSubmitted() && ! $form->isValid()) {
            return $form;
        }

        return $dto;
    }

    /**
     * Creates a Field instance.
     */
    abstract protected function createField(string $fieldName): DoctrineFieldInterface;

    /**
     * Gets the identifier field names from doctrine metadata.
     *
     * @return string[]
     */
    abstract protected function getIdentifierFieldNames(): array;

    /**
     * Parses the ordering expression for continuation token.
     *
     * @return array<string, string>
     *
     * @phpstan-return array<string, 'asc'|'desc'>
     */
    protected function parseOrderings(object $queryBuilder, OrderExpression $ordering): array
    {
        $field = $this->fields[$ordering->getField()];

        if (! ($field instanceof OrderableFieldInterface)) {
            return [];
        }

        $order = $field->getOrder($queryBuilder, $ordering);

        $checksumField = $this->getIdentifierFieldNames()[0];
        if (isset($this->options['continuation_token']['checksum_field'])) {
            $checksumField = $this->options['continuation_token']['checksum_field'];
            if (! $this->fields[$checksumField] instanceof PhpCr\Field && ! $this->fields[$checksumField] instanceof ORM\Field) {
                throw new InvalidArgumentException(sprintf('%s is not a valid field for checksum', $this->options['continuation_token']['checksum_field']));
            }

            $checksumField = $this->fields[$checksumField]->fieldName;
        }

        return [
            $order[0] => $order[1],
            $checksumField => 'asc',
        ];
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

        foreach (['order_field', 'skip_field', 'limit_field', 'default_order'] as $field) {
            $resolver
                ->setDefault($field, null)
                ->setAllowedTypes($field, ['null', 'string']);
        }

        $resolver
            ->setDefault('default_page_size', null)
            ->setAllowedTypes('default_page_size', ['null', 'int'])
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
