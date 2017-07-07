<?php

use JsPhpize\JsPhpizePhug;
use Phug\Renderer;

include_once __DIR__.'/vendor/autoload.php';

$renderer = new Renderer([
    'debug' => true,
    'modules' => [JsPhpizePhug::class],
]);

$vars = [
    'title' => 'Pug',
];
$renderer->displayString('
div
    p
  section
', $vars);
$renderer->displayFile(__DIR__.'/tests/cases/attrs.pug', $vars);
