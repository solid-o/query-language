<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Validator;

use Attribute;
use Solido\QueryLanguage\Walker\Validation\ValidationWalkerInterface;
use Symfony\Component\Validator\Constraint;
use TypeError;

use function array_merge;
use function get_debug_type;
use function is_array;
use function is_callable;
use function is_string;
use function sprintf;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Expression extends Constraint
{
    /**
     * @var string|callable|ValidationWalkerInterface|null
     * @Required()
     */
    public $walker;

    /**
     * {@inheritDoc}
     *
     * @param mixed $walker
     */
    public function __construct(mixed $walker, array|null $groups = null, $payload = null, array $options = [])
    {
        if (is_array($walker)) {
            $options = array_merge($walker, $options);
        } elseif ($walker !== null && ! is_string($walker) && ! is_callable($walker) && ! $walker instanceof ValidationWalkerInterface) {
            throw new TypeError(sprintf('"%s": Expected argument $walker to be either a string, a callable, an instance of %s or an array, got "%s".', __METHOD__, ValidationWalkerInterface::class, get_debug_type($walker)));
        } else {
            $options['walker'] = $walker;
        }

        parent::__construct($options, $groups, $payload);
    }

    public function getDefaultOption(): string
    {
        return 'walker';
    }
}
