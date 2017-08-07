<?php

namespace Phug\Test\Profiler;

use Phug\Renderer\Profiler\Dump;

/**
 * @coversDefaultClass \Phug\Renderer\Profiler\Dump
 */
class DumpTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers ::<public>
     * @covers ::getExposedProperties
     */
    public function testDump()
    {
        $dump = function ($value) {
            return (new Dump($value))->dump();
        };

        self::assertSame('1', $dump(1));
        self::assertSame('true', $dump(true));
        self::assertSame('NULL', $dump(null));
    }
}
