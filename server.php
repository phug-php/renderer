<?php

use JsPhpize\JsPhpizePhug;
use Phug\Renderer;

include_once __DIR__.'/vendor/autoload.php';

$renderer = new Renderer([
    'debug'   => true,
    'modules' => [JsPhpizePhug::class],
]);

$vars = [
    'title'  => 'Pug',
    'helper' => function () {
        return new DoesNotExists();
    },
];
$renderer->displayFile(__DIR__.'/tests/utils/error-track.pug', $vars);
