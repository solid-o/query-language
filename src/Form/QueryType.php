<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Form;

use Solido\Pagination\PageOffset;
use Solido\QueryLanguage\Expression\OrderExpression;
use Solido\QueryLanguage\Form\DTO\Query;
use Solido\QueryLanguage\Processor\FieldInterface;
use Solido\QueryLanguage\Validator\Expression;
use Solido\QueryLanguage\Walker\Validation\OrderWalker;
use Solido\QueryLanguage\Walker\Validation\ValidationWalkerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

use function array_keys;
use function assert;

class QueryType extends AbstractType
{
    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['order_field'] !== null) {
            $builder->add($options['order_field'], FieldType::class, [
                'data_class' => null,
                'property_path' => 'ordering',
                'constraints' => [
                    new Expression($options['order_validation_walker']),
                ],
            ]);

            if (isset($options['default_order'])) {
                $default = $options['default_order'];
                $field = $options['order_field'];
                $builder->addEventListener(
                    FormEvents::PRE_SUBMIT,
                    static function (FormEvent $event) use ($default, $field): void {
                        $data = $event->getData();
                        if (isset($data[$field])) {
                            return;
                        }

                        $data[$field] = $default;
                        $event->setData($data);
                    },
                );
            }
        }

        if ($options['continuation_token_field'] !== null) {
            $builder->add($options['continuation_token_field'], PageTokenType::class, ['property_path' => 'page']);
        }

        if ($options['skip_field'] !== null) {
            $builder->add(
                $builder->create($options['skip_field'], IntegerType::class, ['property_path' => 'page'])->addModelTransformer(new class implements DataTransformerInterface {
                    public function transform(mixed $value): int|null
                    {
                        if ($value instanceof PageOffset) {
                            return $value->getOffset();
                        }

                        return null;
                    }

                    public function reverseTransform(mixed $value): PageOffset|null
                    {
                        if ($value === null) {
                            return null;
                        }

                        $value = (int) $value;
                        if ($value < 0) {
                            throw new TransformationFailedException('Cannot be less than 0');
                        }

                        return new PageOffset($value);
                    }
                }),
            );
        }

        if ($options['limit_field'] !== null) {
            $builder->add($options['limit_field'], IntegerType::class, [
                'property_path' => 'limit',
                'constraints' => [new Range(['min' => 0])],
            ]);
        }

        foreach ($options['fields'] as $key => $field) {
            assert($field instanceof FieldInterface);
            $builder->add($key, FieldType::class, [
                'constraints' => [
                    new Expression($field->getValidationWalker()),
                ],
                'property_path' => 'filters[' . $key . ']',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => Query::class,
                'skip_field' => null,
                'limit_field' => null,
                'continuation_token_field' => null,
                'order_field' => null,
                'default_order' => null,
                'allow_extra_fields' => true,
                'method' => 'GET',
                'orderable_fields' => static fn (Options $options) => array_keys($options['fields']),
                'order_validation_walker' => static fn (Options $options) => new OrderWalker($options['orderable_fields']),
            ])
            ->setAllowedTypes('skip_field', ['null', 'string'])
            ->setAllowedTypes('limit_field', ['null', 'string'])
            ->setAllowedTypes('continuation_token_field', ['null', 'string'])
            ->setAllowedTypes('order_field', ['null', 'string'])
            ->setAllowedTypes('default_order', ['null', OrderExpression::class])
            ->setAllowedTypes('order_validation_walker', ['null', ValidationWalkerInterface::class])
            ->setRequired('fields');
    }
}
