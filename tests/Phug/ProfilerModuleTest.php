<?php

namespace Phug\Test;

use Phug\Formatter;
use Phug\Renderer;

/**
 * @coversDefaultClass Phug\Renderer\Profiler\ProfilerModule
 */
class ProfilerModuleTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers ::<public>
     * @covers \Phug\Renderer::__construct
     */
    public function testRenderEvent()
    {
        $renderer = new Renderer([
            'enable_profiler' => true,
        ]);

        self::assertSame(implode("\n", [
            'x profiler',
            'x renderer.render',
            'x renderer.html',
        ]), preg_replace('/^\d?(\.\d+)?\s+(\S)/m', 'x $2', $renderer->render('div')));
    }
}
