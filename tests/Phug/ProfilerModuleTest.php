<?php

namespace Phug\Test;

use Phug\Renderer;

/**
 * @coversDefaultClass Phug\Renderer\Profiler\ProfilerModule
 */
class ProfilerModuleTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers ::record
     * @covers ::renderProfile
     * @covers ::<public>
     * @covers \Phug\Renderer::__construct
     */
    public function testRenderProfiler()
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

    /**
     * @covers ::record
     * @covers ::renderProfile
     * @covers ::<public>
     * @covers \Phug\Renderer::__construct
     */
    public function testDisplayProfiler()
    {
        $renderer = new Renderer([
            'enable_profiler' => true,
        ]);
        ob_start();
        $renderer->display('div');
        $contents = ob_get_contents();
        ob_end_clean();

        self::assertSame(implode("\n", [
            'x profiler',
            'x renderer.render',
            'x renderer.html',
        ]), preg_replace('/^\d?(\.\d+)?\s+(\S)/m', 'x $2', $contents));
    }
}
