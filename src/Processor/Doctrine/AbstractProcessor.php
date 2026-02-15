<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Processor\Doctrine;

use InvalidArgumentException;
use Refugis\DoctrineExtra\ObjectIteratorInterface;
use Solido\QueryLanguage\Form\DTO\Query;
use Solido\QueryLanguage\Processor\AbstractProcessor as BaseAbstractProcessor;
use Solido\QueryLanguage\Processor\Doctrine\FieldInterface as DoctrineFieldInterface;
use Solido\QueryLanguage\Processor\FieldInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function method_exists;
use function sprintf;

abstract class AbstractProcessor extends BaseAbstractProcessor
{
    /**
     * Adds a field to this list processor.
     *
     * @param array<string|callable|null>|FieldInterface $options
     *
     * @return $this
     */
    public function addField(string $name, array|FieldInterface $options = []): self
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
                'to_many_strategy' => null,
            ])
            ->setAllowedTypes('field_name', 'string')
            ->setAllowedTypes('walker', ['null', 'string', 'callable'])
            ->setAllowedTypes('validation_walker', ['null', 'string', 'callable'])
            ->setAllowedTypes('to_many_strategy', ['null', 'string'])
            ->resolve($options);

        $field = $this->createField($options['field_name']);

        if ($options['walker'] !== null) {
            $field->customWalker = $options['walker']; /* @phpstan-ignore-line */
        }

        if ($options['validation_walker'] !== null) {
            $field->validationWalker = $options['validation_walker']; /* @phpstan-ignore-line */
        }

        if ($options['to_many_strategy'] !== null && method_exists($field, 'setToManyStrategy')) {
            $field->setToManyStrategy($options['to_many_strategy']);
        }

        $this->fields[$name] = $field;

        return $this;
    }

    /**
     * Creates a Field instance.
     */
    abstract protected function createField(string $fieldName): DoctrineFieldInterface;

    /**
     * Builds an ObjectIterator from the given query builder.
     * Allow to make some final/general customization of the query, before firing it to the database engine.
     */
    abstract protected function buildIterator(object $queryBuilder, Query $result): ObjectIteratorInterface;

    protected function getOrderChecksumFieldName(): string
    {
        $checksumField = $this->getIdentifierFieldNames()[0];
        if (isset($this->options['continuation_token']['checksum_field'])) {
            $checksumField = $this->options['continuation_token']['checksum_field'];
            if (! $this->fields[$checksumField] instanceof PhpCr\Field && ! $this->fields[$checksumField] instanceof ORM\Field) {
                throw new InvalidArgumentException(sprintf('%s is not a valid field for checksum', $this->options['continuation_token']['checksum_field']));
            }

            $checksumField = $this->fields[$checksumField]->fieldName;
        }

        return $checksumField;
    }
}
