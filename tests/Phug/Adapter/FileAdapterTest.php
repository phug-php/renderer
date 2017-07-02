<?php

namespace Phug\Test\Adapter;

use Phug\Renderer;
use Phug\Renderer\Adapter\FileAdapter;
use Phug\Test\AbstractRendererTest;

/**
 * @coversDefaultClass \Phug\Renderer\Adapter\FileAdapter
 */
class FileAdapterTest extends AbstractRendererTest
{
    /**
     * @covers ::<public>
     * @covers ::createTemporaryFile
     * @covers ::getCompiledFile
     */
    public function testRender()
    {
        $renderer = new Renderer([
            'renderer_adapter' => FileAdapter::class,
        ]);

        self::assertSame('<p>Hello</p>', $renderer->renderString('p=$message', [
            'message' => 'Hello',
        ]));
    }
}
