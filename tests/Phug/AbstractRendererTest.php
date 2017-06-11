<?php

namespace Phug\Test;

use JsPhpize\JsPhpizePhug;
use Phug\Renderer;

abstract class AbstractRendererTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Renderer
     */
    protected $renderer;

    public function setUp()
    {
        include_once __DIR__.'/Date.php';
        $lastCompiler = null;
        $this->renderer = new Renderer();
        $this->renderer->setOptionsRecursive([
            'basedir'          => __DIR__.'/..',
            'pretty'           => true,
            'compiler_options' => [
                'modules' => [JsPhpizePhug::class],
            ],
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
