<?php declare(strict_types=1);

namespace Solido\QueryLanguage\Form\DTO;

use Solido\Pagination\PageToken;
use Solido\QueryLanguage\Expression\ExpressionInterface;
use Solido\QueryLanguage\Expression\OrderExpression;
use Symfony\Component\Validator\Constraints as Assert;

class Query
{
    public ?PageToken $pageToken;
    public ?int $skip;
    public ?int $limit;

    /**
     * @var ExpressionInterface[]
     */
    public array $filters;

    /**
     * @Assert\Type(OrderExpression::class)
     */
    public ?OrderExpression $ordering;

    public function __construct()
    {
        $this->filters = [];
        $this->ordering = null;
        $this->pageToken = null;
        $this->skip = null;
        $this->limit = null;
    }
}
