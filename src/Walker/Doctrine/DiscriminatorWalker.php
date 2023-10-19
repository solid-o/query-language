<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Walker\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Solido\QueryLanguage\Expression\Literal\LiteralExpression;
use Solido\QueryLanguage\Expression\ValueExpression;

class DiscriminatorWalker extends DqlWalker
{
    private ClassMetadata $rootEntity;

    public function __construct(QueryBuilder $queryBuilder, string $field)
    {
        parent::__construct($queryBuilder, $field);

        $entityManager = $this->queryBuilder->getEntityManager();
        $this->rootEntity = $entityManager->getClassMetadata($this->queryBuilder->getRootEntities()[0]);
    }

    public function walkLiteral(LiteralExpression $expression): mixed
    {
        $value = $expression->getValue();

        return $this->rootEntity->discriminatorMap[$value];
    }

    public function walkComparison(string $operator, ValueExpression $expression): mixed
    {
        return new Expr\Comparison($this->field, 'INSTANCE OF', $expression->dispatch($this));
    }
}
