<?php

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__])
    ->exclude(['docker', 'fixtures', 'vendor', 'var'])
    ->notPath('/cache/')
;

return (new PhpCsFixer\Config())
    ->setFinder($finder)
    ->setRules([
        '@PSR2' => true,
        '@Symfony' => true,
        'psr0' => false,
        'array_syntax' => ['syntax' => 'short'],
        'concat_space' => ['spacing' => 'none'],
        'blank_line_after_opening_tag' => false,
        'lowercase_cast' => true,
        'lowercase_constants' => true,
        'lowercase_keywords' => true,
        'no_trailing_comma_in_singleline_array' => true,
        'no_unused_imports' => true,
        'not_operator_with_successor_space' => true,
        'ordered_imports' => true,
        'declare_strict_types' => true,
        'native_function_invocation' => true,
        'php_unit_test_case_static_method_calls' => ['call_type' => 'self'],
    ])
;
