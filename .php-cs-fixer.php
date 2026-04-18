<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->notPath('bootstrap/cache')
    ->notPath('storage')
    ->notPath('vendor')
    ->in(__DIR__)
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
;

$config = new Config();

return $config->setRules([
    '@PhpCsFixer'            => true,
    'binary_operator_spaces' => [
        'operators' => [
            '=>' => 'align_single_space_minimal',
            '='  => 'align_single_space_minimal',
        ],
    ],
])
    ->setFinder($finder)
    ->setUsingCache(false)
;
