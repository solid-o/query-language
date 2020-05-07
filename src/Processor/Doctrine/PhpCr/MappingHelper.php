<?php

declare(strict_types=1);

namespace Solido\QueryLanguage\Processor\Doctrine\PhpCr;

use Doctrine\ODM\PHPCR\Mapping\ClassMetadata;
use function array_key_last;
use function assert;
use function explode;
use function in_array;
use function Safe\substr;
use function strlen;
use function strrev;
use function substr_count;

final class MappingHelper
{
    /**
     * Processes PHPCR ODM mapping finding the root field by $fieldName.
     * Returns the root field (if found) and the rest part as an array of strings.
     *
     * @return mixed[]
     *
     * @phpstan-return array{array{id?: bool, fieldName: string, type?: string, targetDocument?: string, sourceDocument?: string}, string|null}
     */
    public static function processFieldName(ClassMetadata $classMetadata, string $fieldName): array
    {
        $dots = substr_count($fieldName, '.');
        assert($dots !== false);

        $revFieldName = strrev($fieldName);

        $rootField = null;
        $rest = null;
        for ($i = 1; $i <= $dots + 1; ++$i) {
            $field = explode('.', $revFieldName, $i);
            $field = strrev($field[array_key_last($field)]);

            $rest = strlen($field) !== strlen($fieldName) ? substr($fieldName, strlen($field) + 1) : null;

            if ($classMetadata->nodename === $field || in_array($field, $classMetadata->fieldMappings, true)) {
                $rootField = $classMetadata->mappings[$field];
            } elseif ($classMetadata->hasAssociation($field)) {
                $rootField = $classMetadata->getAssociation($field);
            }

            if ($rootField !== null) {
                break;
            }
        }

        return [$rootField, $rest];
    }
}
