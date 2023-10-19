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
use Solido\DataMapper\DataMapperFactory;
use Solido\DataMapper\Exception\MappingErrorException;
use Solido\Pagination\Doctrine\PhpCr\PagerIterator;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Form\DTO\Query;
use Solido\QueryLanguage\Processor\Doctrine\AbstractProcessor;
use Solido\QueryLanguage\Processor\Doctrine\FieldInterface;

use function assert;

class Processor extends AbstractProcessor
{
    private ClassMetadata $rootDocument;
    private string $rootAlias;

    /** @param array<string, mixed> $options */
    public function __construct(
        private readonly QueryBuilder $queryBuilder,
        private readonly DocumentManagerInterface $documentManager,
        DataMapperFactory $dataMapperFactory,
        array $options = [],
    ) {
        parent::__construct($dataMapperFactory, $options);

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
     * {@inheritDoc}
     */
    protected function getIdentifierFieldNames(): array
    {
        return $this->rootDocument->getIdentifierFieldNames();
    }

    /**
     * Processes and validates the request, adds the filters to the query builder and
     * returns the iterator with the results.
     *
     * @throws MappingErrorException
     */
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
        foreach ($filters as $key => $expr) {
            $field = $this->fields[$key];
            $field->addCondition($this->queryBuilder, $expr);
        }
    }
}
