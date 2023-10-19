<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Form\DTO;

use Solido\Pagination\PageNumber;
use Solido\Pagination\PageOffset;
use Solido\Pagination\PageToken;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\OrderExpression;
use Symfony\Component\Validator\Constraints as Assert;

class Query
{
    public PageToken|PageOffset|PageNumber|null $page;
    public int|null $limit;

    /** @var ExpressionInterface[] */
    public array $filters;

    /** @Assert\Type(OrderExpression::class) */
    public OrderExpression|null $ordering;

    public function __construct()
    {
        $this->filters = [];
        $this->ordering = null;
        $this->page = null;
        $this->limit = null;
    }
}
