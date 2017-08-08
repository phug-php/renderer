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
     * @covers \Phug\Renderer\Profiler\TokenDump::<public>
     * @covers \Phug\Renderer\Profiler\LinkDump::<public>
     * @covers \Phug\Renderer\Profiler\LinkDump::initProperties
     * @covers \Phug\Renderer\Profiler\Profile::<public>
     * @covers \Phug\Renderer\Profiler\Profile::calculateIndex
     * @covers \Phug\Renderer\Profiler\Profile::getProcesses
     * @covers \Phug\Renderer\Profiler\Profile::getDuration
     * @covers \Phug\Renderer\Profiler\LinkedProcesses::<public>
     * @covers \Phug\Renderer\Profiler\LinkedProcesses::getEventLink
     * @covers \Phug\Renderer\Profiler\LinkedProcesses::getProfilerEvent
     * @covers \Phug\Renderer::__construct
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::initDebugOptions
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

        $renderer = new Renderer([
            'enable_profiler' => true,
            'profiler'        => [
                'time_precision' => 7,
                'dump_event'     => function () {
                    return '-void-dump-';
                },
            ],
        ]);
        $render = $renderer->render("mixin foo\n  | Hello\n+foo");

        self::assertRegExp('/\+foo\s+parsing\s*<br>\s*[\.\d]+µs/', $render);
        self::assertRegExp('/text\s+parsing\s*<br>\s*[\.\d]+µs/', $render);
        self::assertRegExp('/mixin\s+foo\s+parsing\s*<br>\s*[\.\d]+µs/', $render);
    }

    /**
     * @group profiler
     * @covers ::record
     * @covers ::renderProfile
     * @covers ::cleanupProfilerNodes
     * @covers ::appendParam
     * @covers ::appendNode
     * @covers ::<public>
     * @covers \Phug\Renderer\Profiler\TokenDump::<public>
     * @covers \Phug\Renderer\Profiler\LinkDump::<public>
     * @covers \Phug\Renderer\Profiler\LinkDump::initProperties
     * @covers \Phug\Renderer\Profiler\Profile::<public>
     * @covers \Phug\Renderer\Profiler\Profile::calculateIndex
     * @covers \Phug\Renderer\Profiler\Profile::getProcesses
     * @covers \Phug\Renderer\Profiler\Profile::getDuration
     * @covers \Phug\Renderer\Profiler\LinkedProcesses::<public>
     * @covers \Phug\Renderer\Profiler\LinkedProcesses::getEventLink
     * @covers \Phug\Renderer\Profiler\LinkedProcesses::getProfilerEvent
     * @covers \Phug\Renderer::__construct
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::initDebugOptions
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
     * @covers \Phug\Renderer\Profiler\TokenDump::<public>
     * @covers \Phug\Renderer\Profiler\LinkDump::<public>
     * @covers \Phug\Renderer\Profiler\LinkDump::initProperties
     * @covers \Phug\Renderer\Profiler\Profile::<public>
     * @covers \Phug\Renderer\Profiler\Profile::calculateIndex
     * @covers \Phug\Renderer\Profiler\Profile::getProcesses
     * @covers \Phug\Renderer\Profiler\LinkedProcesses::<public>
     * @covers \Phug\Renderer\Profiler\LinkedProcesses::getEventLink
     * @covers \Phug\Renderer\Profiler\LinkedProcesses::getProfilerEvent
     * @covers \Phug\Renderer\Profiler\Profile::getDuration
     * @covers \Phug\Renderer::__construct
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::initDebugOptions
     */
    public function testCustomDump()
    {
        $renderer = new Renderer([
            'enable_profiler' => true,
        ]);
        $renderer->setOption('profiler.dump_event', 'get_class');
        /* @var ProfilerModule $profiler */
        $profiler = array_filter($renderer->getModules(), function ($module) {
            return $module instanceof ProfilerModule;
        })[0];

        self::assertInstanceOf(ProfilerModule::class, $profiler);

        $renderer->render('p');

        self::assertGreaterThan(1, count($profiler->getEvents()));

        $profiler->reset();

        self::assertCount(0, $profiler->getEvents());

        $render = $renderer->render('div');

        self::assertContains('Phug\\Compiler\\Event\\NodeEvent', $render);
    }

    /**
     * @group profiler
     * @covers ::reset
     * @covers ::initialize
     * @covers ::getFunctionDump
     * @covers ::<public>
     * @covers \Phug\Renderer\Profiler\TokenDump::<public>
     * @covers \Phug\Renderer\Profiler\LinkDump::<public>
     * @covers \Phug\Renderer\Profiler\LinkDump::initProperties
     * @covers \Phug\Renderer\Profiler\Profile::<public>
     * @covers \Phug\Renderer\Profiler\Profile::calculateIndex
     * @covers \Phug\Renderer\Profiler\Profile::getProcesses
     * @covers \Phug\Renderer\Profiler\Profile::getDuration
     * @covers \Phug\Renderer\Profiler\LinkedProcesses::<public>
     * @covers \Phug\Renderer\Profiler\LinkedProcesses::getEventLink
     * @covers \Phug\Renderer\Profiler\LinkedProcesses::getProfilerEvent
     * @covers \Phug\Renderer::__construct
     * @covers \Phug\Renderer\Partial\Debug\DebuggerTrait::initDebugOptions
     */
    public function testEventVarDump()
    {
        if (defined('HHVM_VERSION')) {
            self::markTestSkipped('var_dump test update disabled for HHVM.');

            return;
        }

        $renderer = new Renderer([
            'enable_profiler' => true,
        ]);
        $renderer->setOption('profiler.dump_event', 'var_dump');
        /* @var ProfilerModule $profiler */
        $profiler = array_filter($renderer->getModules(), function ($module) {
            return $module instanceof ProfilerModule;
        })[0];

        self::assertInstanceOf(ProfilerModule::class, $profiler);

        $renderer->render('p');

        self::assertGreaterThan(1, count($profiler->getEvents()));

        $profiler->reset();

        self::assertCount(0, $profiler->getEvents());

        $render = $renderer->render('div');

        self::assertRegExp('/class\\s+Phug\\\\Parser\\\\Node\\\\DocumentNode#\\d+\\s+\\(\\d+\\)\\s+\\{/', $render);
    }
}
