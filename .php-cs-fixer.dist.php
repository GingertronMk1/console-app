<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PhpCsFixer:risky' => true,
    ])
    ->setFinder($finder)
;
