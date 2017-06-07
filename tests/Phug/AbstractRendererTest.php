<?php

namespace Phug\Test;

use JsPhpize\JsPhpize;
use Phug\Compiler;
use Phug\Renderer;

abstract class AbstractRendererTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Renderer
     */
    protected $renderer;

    public function setUp()
    {
        $lastCompiler = null;
        $this->renderer = new Renderer([
            'basedir' => __DIR__.'/..',
            'pretty'  => true,
            'compiler_options' => [
                'pre_compile' => [
                    function ($pugCode, Compiler $compiler) use (&$lastCompiler) {
                        $lastCompiler = $compiler;
                        $compiler->setOption('jsphpize_engine', new JsPhpize([
                            'catchDependencies' => true,
                        ]));

                        return $pugCode;
                    }
                ],
                'post_compile' => [
                    function ($phpCode, Compiler $compiler) {
                        /** @var JsPhpize $jsPhpize */
                        $jsPhpize = $compiler->getOption('jsphpize_engine');
                        $phpCode = $compiler->getFormatter()->handleCode($jsPhpize->compileDependencies()).$phpCode;
                        $jsPhpize->flushDependencies();
                        $compiler->unsetOption('jsphpize_engine');

                        return $phpCode;
                    }
                ],
                'formatter_options' => [
                    'patterns' => [
                        'transform_expression' => function ($jsCode) use (&$lastCompiler) {
                            /** @var JsPhpize $jsPhpize */
                            $jsPhpize = $lastCompiler->getOption('jsphpize_engine');

                            return $jsPhpize->compile($jsCode);
                        },
                    ],
                ],
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
