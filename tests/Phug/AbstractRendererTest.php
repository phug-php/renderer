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

    public static function flatContent($content)
    {
        return implode('', array_map('trim', preg_split('/\r|\n/', self::linuxLines($content))));
    }

    public static function linuxLines($content)
    {
        return str_replace(["\r\n", '/><', ' />'], ["\n", "/>\n<", '/>'], trim($content));
    }

    public static function assertSameLines($expected, $actual, $message = null)
    {
        $flatExpected = self::flatContent($expected);
        $flatActual = self::flatContent($actual);
        if ($flatExpected === $flatActual) {
            self::assertSame($flatExpected, $flatActual, $message);

            return;
        }
        $expected = self::linuxLines($expected);
        $actual = self::linuxLines($actual);

        self::assertSame($expected, $actual, $message);
    }
}
