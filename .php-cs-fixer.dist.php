<?php

$rules = [
    '@Symfony'               => true,
    'binary_operator_spaces' => [
        'operators' => [
            '=>' => 'align',
        ],
    ],
    'declare_strict_types'   => true,
    'concat_space'           => ['spacing' => 'none'],
];

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__.'/src', __DIR__.'/tests']);

return (new PhpCsFixer\Config())
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setRules($rules)
    ->setUsingCache(true);
