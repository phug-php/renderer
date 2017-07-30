<?php

use Phug\Renderer;

include __DIR__.'/vendor/autoload.php';

$renderer = new Renderer([
    'enable_profiler' => true,
    'filters'         => [
        'verbatim' => function ($contents) {
            return $contents;
        },
    ]
]);

$renderer->displayFile(__DIR__.'/tests/cases/includes.pug');
