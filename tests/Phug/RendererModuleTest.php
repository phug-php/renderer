<?php

namespace Phug\Test;

use Phug\Formatter;

/**
 * @coversDefaultClass Phug\AbstractRendererModule
 */
class RendererModuleTest extends \PHPUnit_Framework_TestCase
{
    public function testModule()
    {
        include_once __DIR__.'/TestRendererModule.php';
        $module = new TestRendererModule(new Formatter());

        self::assertTrue(is_array($module->getEventListeners()));
    }
}
