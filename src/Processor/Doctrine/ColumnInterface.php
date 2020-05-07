<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Processor\Doctrine;

use Solido\QueryLanguage\Processor\ColumnInterface as BaseColumnInterface;

/**
 * @property string|callable|null $customWalker
 * @property string|callable|null $validationWalker
 */
interface ColumnInterface extends BaseColumnInterface
{
}
