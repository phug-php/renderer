<?php

namespace Phug\Test;

use Phug\Renderer;
use Phug\Renderer\Profiler\ProfilerModule;

/**
 * @coversDefaultClass Phug\Renderer\Profiler\ProfilerModule
 */
class ProfilerModuleTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group profiler
     * @covers ::record
     * @covers ::renderProfile
     * @covers ::cleanupProfilerNodes
     * @covers ::appendParam
     * @covers ::appendNode
     * @covers ::<public>
     * @covers \Phug\Renderer\Profiler\Profile::<public>
     * @covers \Phug\Renderer\Profiler\Profile::getEventLink
     * @covers \Phug\Renderer\Profiler\Profile::getProfilerEvent
     * @covers \Phug\Renderer\Profiler\Profile::getDuration
     * @covers \Phug\Renderer::__construct
     */
    public function testRenderProfiler()
    {
        $renderer = new Renderer([
            'enable_profiler' => true,
        ]);
        $render = $renderer->render('div');

        self::assertRegExp('/div lexing\s*<br>\s*[\.\d]+[µm]?s/', $render);
        self::assertContains('title="div lexing:', $render);
        self::assertRegExp('/div parsing\s*<br>\s*[\.\d]+[µm]?s/', $render);
        self::assertContains('title="div parsing:', $render);
        self::assertRegExp('/div compiling\s*<br>\s*[\.\d]+[µm]?s/', $render);
        self::assertContains('title="div compiling:', $render);
        self::assertRegExp('/div formatting\s*<br>\s*[\.\d]+[µm]?s/', $render);
        self::assertContains('title="div formatting:', $render);
        self::assertRegExp('/div rendering\s*<br>\s*[\.\d]+[µm]?s/', $render);
        self::assertContains('title="div rendering:', $render);
    }

    /**
     * @group profiler
     * @covers ::record
     * @covers ::renderProfile
     * @covers ::cleanupProfilerNodes
     * @covers ::appendParam
     * @covers ::appendNode
     * @covers ::<public>
     * @covers \Phug\Renderer\Profiler\Profile::<public>
     * @covers \Phug\Renderer\Profiler\Profile::getEventLink
     * @covers \Phug\Renderer\Profiler\Profile::getProfilerEvent
     * @covers \Phug\Renderer\Profiler\Profile::getDuration
     * @covers \Phug\Renderer::__construct
     */
    public function testDisplayProfiler()
    {
        $renderer = new Renderer([
            'enable_profiler' => true,
            'profiler'        => [
                'dump_event' => function () {
                    return '-void-dump-';
                },
            ],
        ]);
        ob_start();
        $renderer->display('div');
        $contents = ob_get_contents();
        ob_end_clean();

        self::assertRegExp('/div lexing\s*<br>\s*[\.\d]+[µm]?s/', $contents);
        self::assertContains('title="div lexing:', $contents);
        self::assertRegExp('/div parsing\s*<br>\s*[\.\d]+[µm]?s/', $contents);
        self::assertContains('title="div parsing:', $contents);
        self::assertRegExp('/div compiling\s*<br>\s*[\.\d]+[µm]?s/', $contents);
        self::assertContains('title="div compiling:', $contents);
        self::assertRegExp('/div formatting\s*<br>\s*[\.\d]+[µm]?s/', $contents);
        self::assertContains('title="div formatting:', $contents);
        self::assertRegExp('/div rendering\s*<br>\s*[\.\d]+[µm]?s/', $contents);
        self::assertContains('title="div rendering:', $contents);
        self::assertContains('-void-dump-', $contents);
    }

    /**
     * @group profiler
     * @covers ::reset
     * @covers ::initialize
     * @covers ::getFunctionDump
     * @covers ::<public>
     * @covers \Phug\Renderer\Profiler\Profile::<public>
     * @covers \Phug\Renderer\Profiler\Profile::getEventLink
     * @covers \Phug\Renderer\Profiler\Profile::getProfilerEvent
     * @covers \Phug\Renderer\Profiler\Profile::getDuration
     * @covers \Phug\Renderer::__construct
     */
    public function testCustomDump()
    {
        $renderer = new Renderer([
            'enable_profiler' => true,
        ]);
        $renderer->setOption('profiler.dump_event', 'var_dump');
        /* @var ProfilerModule $profiler */
        $profiler = array_filter($renderer->getModules(), function ($module) {
            return $module instanceof ProfilerModule;
        })[0];

        self::assertInstanceOf(ProfilerModule::class, $profiler);

        $profiler->reset();

        $render = $renderer->render('div');

        self::assertRegExp('/class\\s+Phug\\\\Parser\\\\Node\\\\DocumentNode#\\d+\\s+\\(\\d+\\)\\s+\\{/', $render);
    }
}
