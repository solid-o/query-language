<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Processor\Doctrine\DBAL;

use Doctrine\DBAL\Query\QueryBuilder;
use Refugis\DoctrineExtra\DBAL\RowIterator;
use Refugis\DoctrineExtra\ObjectIteratorInterface;
use Solido\Pagination\Doctrine\DBAL\PagerIterator;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\OrderExpression;
use Solido\QueryLanguage\Processor\Doctrine\AbstractProcessor;
use Solido\QueryLanguage\Processor\Doctrine\FieldInterface;
use Solido\QueryLanguage\Processor\OrderableFieldInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;
use function array_values;
use function assert;
use function explode;
use function is_string;
use function strpos;

class Processor extends AbstractProcessor
{
    private QueryBuilder $queryBuilder;

    /** @var string[] */
    private array $identifierFields;

    /**
     * @param mixed[] $options
     */
    public function __construct(QueryBuilder $queryBuilder, FormFactoryInterface $formFactory, array $options = [])
    {
        parent::__construct($formFactory, $options);

        $this->identifierFields = array_values($this->options['identifiers']);
        $this->queryBuilder = $queryBuilder;
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
        $pageSize = $this->options['default_page_size'] ?? $result->limit;

        if ($result->skip !== null) {
            $this->queryBuilder->setFirstResult($result->skip);
        }

        if ($result->ordering !== null) {
            if ($this->options['continuation_token']) {
                $iterator = new PagerIterator($this->queryBuilder, $this->parseOrderings($this->queryBuilder, $result->ordering));
                $iterator->setToken($result->pageToken);
                if ($pageSize !== null) {
                    $iterator->setPageSize($pageSize);
                }

                return $iterator;
            }

            $sql = $this->queryBuilder->getSQL();
            $direction = $result->ordering->getDirection();
            $fieldName = $this->fields[$result->ordering->getField()]->fieldName;

            $this->queryBuilder = $this->queryBuilder->getConnection()
                ->createQueryBuilder()
                ->select('*')
                ->from('(' . $sql . ')', 'x')
                ->setFirstResult($result->skip ?? 0)
                ->orderBy($fieldName, $direction);
        }

        if ($pageSize !== null) {
            $this->queryBuilder->setMaxResults($pageSize);
        }

        return new RowIterator($this->queryBuilder);
    }

    protected function createField(string $fieldName): FieldInterface
    {
        $tableName = null;
        if (strpos($fieldName, '.') !== false) {
            [$tableName, $fieldName] = explode('.', $fieldName);
        }

        return new Field($fieldName, $tableName);
    }

    /**
     * {@inheritdoc}
     */
    protected function getIdentifierFieldNames(): array
    {
        return $this->identifierFields;
    }

    /**
     * Parses the ordering expression for continuation token.
     *
     * @param QueryBuilder $queryBuilder
     *
     * @return array<string, string>
     *
     * @phpstan-return array<string, 'asc'|'desc'>
     */
    protected function parseOrderings(object $queryBuilder, OrderExpression $ordering): array
    {
        $checksumField = $this->options['continuation_token']['checksum_field'] ?? $this->getIdentifierFieldNames()[0] ?? null;
        $field = $this->fields[$ordering->getField()];

        if (! ($field instanceof OrderableFieldInterface)) {
            return [];
        }

        $order = $field->getOrder($queryBuilder, $ordering);

        if ($checksumField === null) {
            foreach ($this->fields as $field) {
                if ($checksumField === $field) {
                    continue;
                }

                $checksumField = $field;
                break;
            }
        }

        $checksumField = $checksumField instanceof FieldInterface ? $checksumField->fieldName : $checksumField;
        assert(is_string($checksumField));

        return [
            $order[0] => $order[1],
            $checksumField => 'asc',
        ];
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'identifiers' => [],
        ]);

        $resolver->setAllowedTypes('identifiers', 'array');
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
