<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Processor\Doctrine\PhpCr;

use Doctrine\ODM\PHPCR\DocumentManagerInterface;
use Doctrine\ODM\PHPCR\Mapping\ClassMetadata;
use Doctrine\ODM\PHPCR\Query\Builder\AbstractNode;
use Doctrine\ODM\PHPCR\Query\Builder\From;
use Doctrine\ODM\PHPCR\Query\Builder\QueryBuilder;
use Doctrine\ODM\PHPCR\Query\Builder\SourceDocument;
use Refugis\DoctrineExtra\ObjectIteratorInterface;
use Refugis\DoctrineExtra\ODM\PhpCr\DocumentIterator;
use Solido\Pagination\Doctrine\PhpCr\PagerIterator;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Form\DTO\Query;
use Solido\QueryLanguage\Processor\Doctrine\AbstractProcessor;
use Solido\QueryLanguage\Processor\Doctrine\FieldInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

use function array_key_first;
use function assert;

class Processor extends AbstractProcessor
{
    private QueryBuilder $queryBuilder;
    private DocumentManagerInterface $documentManager;
    private ClassMetadata $rootDocument;
    private string $rootAlias;

    /**
     * @param mixed[] $options
     */
    public function __construct(
        QueryBuilder $queryBuilder,
        DocumentManagerInterface $documentManager,
        FormFactoryInterface $formFactory,
        array $options = []
    ) {
        parent::__construct($formFactory, $options);

        $this->queryBuilder = $queryBuilder;
        $this->documentManager = $documentManager;
        $this->fields = [];

        $fromNode = $this->queryBuilder->getChildOfType(AbstractNode::NT_FROM);
        assert($fromNode instanceof From);
        $sourceNode = $fromNode->getChildOfType(AbstractNode::NT_SOURCE);
        assert($sourceNode instanceof SourceDocument);

        /** @phpstan-var class-string $sourceFqn */
        $sourceFqn = $sourceNode->getDocumentFqn();
        $classMetadata = $this->documentManager->getClassMetadata($sourceFqn);
        assert($classMetadata instanceof ClassMetadata);

        $this->rootDocument = $classMetadata;
        $this->rootAlias = $sourceNode->getAlias();
    }

    protected function createField(string $fieldName): FieldInterface
    {
        return new Field($fieldName, $this->rootAlias, $this->rootDocument, $this->documentManager);
    }

    /**
     * {@inheritdoc}
     */
    protected function getIdentifierFieldNames(): array
    {
        return $this->rootDocument->getIdentifierFieldNames();
    }

    /**
     * Processes and validates the request, adds the filters to the query builder and
     * returns the iterator with the results.
     *
     * @return ObjectIteratorInterface|FormInterface
     */
    public function processRequest(Request $request)
    {
        $result = $this->handleRequest($request);
        if ($result instanceof FormInterface) {
            return $result;
        }

        $this->attachToQueryBuilder($result->filters);
        if ($result->skip !== null) {
            $this->queryBuilder->setFirstResult($result->skip);
        }

        return $this->buildIterator($this->queryBuilder, $result);
    }

    protected function buildIterator(object $queryBuilder, Query $result): ObjectIteratorInterface
    {
        assert($queryBuilder instanceof QueryBuilder);
        $pageSize = $result->limit ?? $this->options['default_page_size'];
        if ($this->options['max_page_size'] !== null && $pageSize > $this->options['max_page_size']) {
            $pageSize = $this->options['max_page_size'];
        }

        if ($result->ordering !== null) {
            $ordering = $this->parseOrderings($queryBuilder, $result->ordering);
            if ($ordering && $this->options['continuation_token']) {
                $iterator = new PagerIterator($queryBuilder, $ordering);
                $iterator->setToken($result->pageToken);
                if ($pageSize !== null) {
                    $iterator->setPageSize($pageSize);
                }

                return $iterator;
            }

            $key = array_key_first($ordering);

            if ($key !== null) {
                $fromNode = $queryBuilder->getChildOfType(AbstractNode::NT_FROM);
                assert($fromNode instanceof From);
                $source = $fromNode->getChildOfType(AbstractNode::NT_SOURCE);
                assert($source instanceof SourceDocument);
                $alias = $source->getAlias();

                if ($this->rootDocument->getTypeOfField($key) === 'nodename') {
                    $queryBuilder->orderBy()->{$ordering[$key]}()->localName($alias);
                } else {
                    $queryBuilder->orderBy()->{$ordering[$key]}()->field($alias . '.' . $key);
                }
            }
        }

        if ($pageSize !== null) {
            $queryBuilder->setMaxResults($pageSize);
        }

        return new DocumentIterator($queryBuilder);
    }

    /**
     * Add conditions to query builder.
     *
     * @param array<string, ExpressionInterface> $filters
     */
    private function attachToQueryBuilder(array $filters): void
    {
        foreach ($filters as $key => $expr) {
            $field = $this->fields[$key];
            $field->addCondition($this->queryBuilder, $expr);
        }
    }
}
