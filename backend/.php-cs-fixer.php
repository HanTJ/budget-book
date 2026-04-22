<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->in(__DIR__ . '/public');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'declare_strict_types' => true,
        'strict_param' => true,
        'strict_comparison' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
