<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Processor\Doctrine\ORM;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Refugis\DoctrineExtra\ObjectIteratorInterface;
use Solido\DataMapper\DataMapperFactory;
use Solido\DataMapper\Exception\MappingErrorException;
use Solido\Pagination\Doctrine\ORM\PagerIterator;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Form\DTO\Query;
use Solido\QueryLanguage\Processor\Doctrine\AbstractProcessor;
use Solido\QueryLanguage\Processor\Doctrine\FieldInterface;
use Solido\QueryLanguage\Walker\Doctrine\JsonTextFunction;
use Solido\QueryLanguage\Walker\Validation\ValidationWalkerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function assert;

class Processor extends AbstractProcessor
{
    private string $rootAlias;
    private EntityManagerInterface $entityManager;
    private ClassMetadata $rootEntity;

    /**
     * @param array<string, mixed> $options
     * @phpstan-param array{order_field?: string, skip_field?: string, limit_field?: string, default_order?: string, default_page_size?: int, order_validation_walker?: ValidationWalkerInterface, continuation_token?: bool|array{field:string, checksum_field:string}, to_many_strategy?: string} $options
     */
    public function __construct(
        private readonly QueryBuilder $queryBuilder,
        DataMapperFactory $dataMapperFactory,
        array $options = [],
    ) {
        parent::__construct($dataMapperFactory, $options);

        $this->entityManager = $this->queryBuilder->getEntityManager();
        $configuration = $this->entityManager->getConfiguration();
        if ($configuration->getCustomStringFunction('JSON_TEXT') === null) {
            $configuration->addCustomStringFunction('JSON_TEXT', JsonTextFunction::class);
        }

        $this->rootAlias = $this->queryBuilder->getRootAliases()[0];
        $this->rootEntity = $this->entityManager->getClassMetadata($this->queryBuilder->getRootEntities()[0]);
    }

    protected function createField(string $fieldName): FieldInterface
    {
        return new Field(
            $fieldName,
            $this->rootAlias,
            $this->rootEntity,
            $this->entityManager,
            $this->options['to_many_strategy'],
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function getIdentifierFieldNames(): array
    {
        return $this->rootEntity->getIdentifierColumnNames();
    }

    /** @throws MappingErrorException */
    public function processRequest(object $request): ObjectIteratorInterface
    {
        $result = $this->handleRequest($request);
        $this->attachToQueryBuilder($result->filters);

        return $this->buildIterator($this->queryBuilder, $result);
    }

    protected function buildIterator(object $queryBuilder, Query $result): ObjectIteratorInterface
    {
        assert($queryBuilder instanceof QueryBuilder);
        $pageSize = $result->limit ?? $this->options['default_page_size'];
        if ($this->options['max_page_size'] !== null && $pageSize > $this->options['max_page_size']) {
            $pageSize = $this->options['max_page_size'];
        }

        $ordering = $this->parseOrderings($queryBuilder, $result->ordering);
        $iterator = new PagerIterator($queryBuilder, $ordering);
        $iterator->setCurrentPage($result->page);
        if ($pageSize !== null) {
            $iterator->setPageSize($pageSize);
        }

        return $iterator;
    }

    /**
     * Add conditions to query builder.
     *
     * @param array<string, ExpressionInterface> $filters
     */
    private function attachToQueryBuilder(array $filters): void
    {
        $this->queryBuilder->andWhere('1 = 1');

        foreach ($filters as $key => $expr) {
            $field = $this->fields[$key];
            $field->addCondition($this->queryBuilder, $expr);
        }
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('to_many_strategy', Field::TO_MANY_STRATEGY_SPLIT)
            ->setAllowedTypes('to_many_strategy', 'string')
            ->setAllowedValues('to_many_strategy', [Field::TO_MANY_STRATEGY_SPLIT, Field::TO_MANY_STRATEGY_SHARED]);
    }
}
