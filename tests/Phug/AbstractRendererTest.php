<?php

namespace Phug\Test;

use Exception;
use JsPhpize\Compiler\Exception as CompilerException;
use JsPhpize\JsPhpize;
use JsPhpize\Lexer\Exception as LexerException;
use JsPhpize\Parser\Exception as ParserException;
use Phug\Compiler;
use Phug\Renderer;

abstract class AbstractRendererTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Renderer
     */
    protected $renderer;

    public static function secure($string)
    {
        return htmlspecialchars(
            is_object($string) || is_array($string)
                ? json_encode($string)
                : strval($string)
        );
    }

    public function setUp()
    {
        include_once __DIR__.'/Date.php';
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
                        'html_expression_escape' => '\Phug\Test\AbstractRendererTest::secure(%s)',
                        'transform_expression'   => function ($jsCode) use (&$lastCompiler) {
                            /** @var JsPhpize $jsPhpize */
                            $jsPhpize = $lastCompiler->getOption('jsphpize_engine');

                            try {
                                return $jsPhpize->compile($jsCode);
                            } catch(Exception $e) {
                                if (
                                    $e instanceof LexerException ||
                                    $e instanceof ParserException ||
                                    $e instanceof CompilerException
                                ) {
                                    return $jsCode;
                                }

                                throw $e;
                            }
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
