<?php

namespace Phug\Test;

use Phug\Compiler;
use Phug\CompilerModule;
use Phug\Parser;
use Phug\ParserModule;
use Phug\Renderer;
use Phug\RendererModule;

/**
 * @coversDefaultClass Phug\RendererModule
 */
class RendererModuleTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers ::<public>
     * @covers \Phug\Renderer::__construct
     */
    public function testRendererModule()
    {
        $copy = null;
        $module = new RendererModule();
        $module->onPlug(function ($_renderer) use (&$copy) {
            $copy = $_renderer;
        });
        $renderer = new Renderer([
            'modules' => [$module],
        ]);
        self::assertSame($renderer, $copy);
    }

    /**
     * @group i
     * @covers ::<public>
     * @covers \Phug\Renderer::mergeOptions
     * @covers \Phug\Renderer::__construct
     */
    public function testSubModules()
    {
        $parser1 = null;
        $parser2 = null;
        $compiler = null;
        $compilerModule = new CompilerModule();
        $compilerModule->onPlug(function ($instance) use (&$compiler) {
            $compiler = $instance;
        });
        $parserModule1 = new ParserModule();
        $parserModule1->onPlug(function ($instance) use (&$parser1) {
            $parser1 = $instance;
        });
        $parserModule2 = new ParserModule();
        $parserModule2->onPlug(function ($instance) use (&$parser2) {
            $parser2 = $instance;
        });

        $renderer = new Renderer([
            'modules' => [$compilerModule, $parserModule1],
            'parser_options' => [
                'modules' => [$parserModule2],
            ],
        ]);
        $renderer->renderString('p Hello');

        self::assertInstanceOf(Parser::class, $parser1);
        self::assertInstanceOf(Parser::class, $parser2);
        self::assertInstanceOf(Compiler::class, $compiler);
    }
}
