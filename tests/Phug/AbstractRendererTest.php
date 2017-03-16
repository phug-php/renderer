<?php

namespace Phug\Test;

use Phug\Renderer;

abstract class AbstractRendererTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Renderer
     */
    protected $renderer;

    public function setUp()
    {
        $this->renderer = new Renderer([
            'basedir' => __DIR__.'/..',
            'pretty'  => true,
        ]);
    }

    static public function linuxLines($content)
    {
        return str_replace("\r\n", "\n", trim($content));
    }

    static public function asssertSameLines($expected, $actual)
    {
        self::assertSame(self::linuxLines($expected), self::linuxLines($actual));
    }
}
