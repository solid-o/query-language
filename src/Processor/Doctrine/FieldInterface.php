<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Processor\Doctrine;

use Solido\QueryLanguage\Processor\FieldInterface as BaseFieldInterface;

/**
 * @property string|callable|null $customWalker
 * @property string|callable|null $validationWalker
 */
interface FieldInterface extends BaseFieldInterface
{
}
