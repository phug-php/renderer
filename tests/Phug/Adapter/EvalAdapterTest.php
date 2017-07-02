<?php

namespace Phug\Test\Adapter;

use Phug\Renderer;
use Phug\Renderer\Adapter\EvalAdapter;
use Phug\Test\AbstractRendererTest;

/**
* @coversDefaultClass \Phug\Renderer\Adapter\EvalAdapter
*/
class EvalAdapterTest extends AbstractRendererTest
{
    /**
     * @covers ::display
     */
    public function testRender()
    {
        $renderer = new Renderer([
            'renderer_adapter' => EvalAdapter::class,
        ]);

        self::assertSame('<p>Hello</p>', $renderer->renderString('p Hello'));
    }
}
