<?php

namespace Phug\Test;

use Phug\Renderer;
use Phug\RendererModule;

/**
 * @coversDefaultClass Phug\RendererModule
 */
class RendererModuleTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers ::<public>
     */
    public function testModule()
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
}
