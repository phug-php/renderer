<?php

namespace Phug\Test;

/**
 * @coversDefaultClass \Phug\Renderer
 */
class RendererTest extends AbstractRendererTest
{
    /**
     * @covers ::renderString
     */
    public function testRenderString()
    {
        $actual = trim($this->renderer->renderString('#{"p"}.foo Hello'));
        self::assertSame('<p class="foo">Hello</p>', $actual);
    }
}