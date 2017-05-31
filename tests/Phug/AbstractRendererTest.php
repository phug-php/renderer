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

    public static function linuxLines($content)
    {
        return str_replace(array("\r\n", '/><', ' />'), array("\n", "/>\n<", '/>'), trim($content));
    }

    public static function assertSameLines($expected, $actual, $message = null)
    {
        self::assertSame(self::linuxLines($expected), self::linuxLines($actual), $message);
    }
}
