<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->exclude('vendor')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => true,
        'no_unused_imports' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'blank_line_before_statement' => [
            'statements' => ['return', 'throw', 'try', 'declare'],
        ],
        'single_line_comment_style' => [
            'comment_types' => ['hash'],
        ],
        'phpdoc_align' => [
            'align' => 'left',
        ],
    ])
    ->setFinder($finder)
; 