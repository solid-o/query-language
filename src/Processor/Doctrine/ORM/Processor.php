<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Processor\Doctrine\ORM;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Refugis\DoctrineExtra\ObjectIteratorInterface;
use Refugis\DoctrineExtra\ORM\EntityIterator;
use Solido\Pagination\Doctrine\ORM\PagerIterator;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Processor\Doctrine\AbstractProcessor;
use Solido\QueryLanguage\Processor\Doctrine\FieldInterface;
use Solido\QueryLanguage\Walker\Validation\ValidationWalkerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

use function array_key_first;
use function assert;

class Processor extends AbstractProcessor
{
    private string $rootAlias;
    private QueryBuilder $queryBuilder;
    private EntityManagerInterface $entityManager;
    private ClassMetadata $rootEntity;

    /**
     * @param mixed[] $options
     *
     * @phpstan-param array{order_field?:?string, skip_field?:?string, limit_field?:?string, default_order?:?string, default_page_size?:?int, order_validation_walker?:?ValidationWalkerInterface, continuation_token?:bool|array{field:string, checksum_field:string}} $options
     */
    public function __construct(QueryBuilder $queryBuilder, FormFactoryInterface $formFactory, array $options = [])
    {
        parent::__construct($formFactory, $options);

        $this->queryBuilder = $queryBuilder;
        $this->entityManager = $this->queryBuilder->getEntityManager();

        $this->rootAlias = $this->queryBuilder->getRootAliases()[0];
        $this->rootEntity = $this->entityManager->getClassMetadata($this->queryBuilder->getRootEntities()[0]);
    }

    protected function createField(string $fieldName): FieldInterface
    {
        return new Field($fieldName, $this->rootAlias, $this->rootEntity, $this->entityManager);
    }

    /**
     * {@inheritdoc}
     */
    protected function getIdentifierFieldNames(): array
    {
        return $this->rootEntity->getIdentifierColumnNames();
    }

    /**
     * @return ObjectIteratorInterface|FormInterface
     */
    public function processRequest(Request $request)
    {
        $result = $this->handleRequest($request);
        if ($result instanceof FormInterface) {
            return $result;
        }

        $this->attachToQueryBuilder($result->filters);
        $pageSize = $result->limit ?? $this->options['default_page_size'];
        if ($this->options['max_page_size'] !== null && $pageSize > $this->options['max_page_size']) {
            $pageSize = $this->options['max_page_size'];
        }

        if ($result->skip !== null) {
            $this->queryBuilder->setFirstResult($result->skip);
        }

        if ($result->ordering !== null) {
            $ordering = $this->parseOrderings($this->queryBuilder, $result->ordering);
            if ($ordering && $this->options['continuation_token']) {
                $iterator = new PagerIterator($this->queryBuilder, $ordering);
                $iterator->setToken($result->pageToken);
                if ($pageSize !== null) {
                    $iterator->setPageSize($pageSize);
                }

                return $iterator;
            }

            $key = array_key_first($ordering);
            if ($key !== null) {
                $alias = $this->queryBuilder->getRootAliases()[0];
                $this->queryBuilder->orderBy($alias . '.' . $key, $ordering[$key]);
            }
        }

        if ($pageSize !== null) {
            $this->queryBuilder->setMaxResults($pageSize);
        }

        return $this->buildIterator($this->queryBuilder);
    }

    protected function buildIterator(object $queryBuilder): ObjectIteratorInterface
    {
        assert($queryBuilder instanceof QueryBuilder);

        return new EntityIterator($queryBuilder);
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
}
