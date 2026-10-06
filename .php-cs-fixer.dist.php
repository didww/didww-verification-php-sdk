<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(array_filter([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/examples'], 'is_dir'))
    ->append([__FILE__]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => true,
    ])
    ->setFinder($finder);
