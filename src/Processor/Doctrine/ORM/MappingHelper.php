<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Processor\Doctrine\ORM;

use Doctrine\ORM\Mapping\ClassMetadata;

use function array_key_last;
use function assert;
use function explode;
use function Safe\substr;
use function strlen;
use function strrev;
use function substr_count;

final class MappingHelper
{
    /**
     * Processes ORM mapping finding the root field by $fieldName.
     * Returns the root field (if found) and the rest part as an array of strings.
     *
     * @return mixed[]
     */
    public static function processFieldName(ClassMetadata $classMetadata, string $fieldName): array
    {
        $dots = substr_count($fieldName, '.');
        assert($dots !== false);

        $revFieldName = strrev($fieldName);

        $rootField = $classMetadata->fieldMappings[$fieldName] ??
            $classMetadata->associationMappings[$fieldName] ?? null;
        $rest = null;

        for ($i = 1; $i <= $dots + 1; ++$i) {
            $field = explode('.', $revFieldName, $i);
            $field = strrev($field[array_key_last($field)]);

            $rest = strlen($field) !== strlen($fieldName) ? substr($fieldName, strlen($field) + 1) : null;

            $rootField = $classMetadata->fieldMappings[$field] ??
                $classMetadata->associationMappings[$field] ?? null;

            if ($rootField !== null) {
                break;
            }
        }

        return [$rootField, $rest];
    }
}
