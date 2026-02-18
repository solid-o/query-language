<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Validator;

use Attribute;
use Solido\QueryLanguage\Walker\Validation\ValidationWalkerInterface;
use Symfony\Component\Validator\Constraint;
use TypeError;

use function get_debug_type;
use function is_callable;
use function is_string;
use function sprintf;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Expression extends Constraint
{
    /**
     * @var string|callable|ValidationWalkerInterface|null
     */
    public $walker;

    /**
     * {@inheritDoc}
     *
     * @param mixed $walker
     */
    public function __construct(mixed $walker, array|null $groups = null, $payload = null)
    {
        if ($walker !== null && ! is_string($walker) && ! is_callable($walker) && ! $walker instanceof ValidationWalkerInterface) {
            throw new TypeError(sprintf('"%s": Expected argument $walker to be either a string, a callable, an instance of %s or an array, got "%s".', __METHOD__, ValidationWalkerInterface::class, get_debug_type($walker)));
        }

        parent::__construct(null, $groups, $payload);
    }

    public function getDefaultOption(): string
    {
        return 'walker';
    }
}
