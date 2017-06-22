<?php

namespace Phug\Test;

use cebe\markdown\Markdown;
use JsPhpize\JsPhpizePhug;
use NodejsPhpFallback\CoffeeScript;
use NodejsPhpFallback\Less;
use NodejsPhpFallback\Stylus;
use NodejsPhpFallback\Uglify;
use Phug\Renderer;
use stdClass;

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
        $uglify = function ($contents) {
            $engine = new Uglify($contents);

            return $engine->getResult();
        };
        $this->renderer = new Renderer([
            'basedir'          => __DIR__.'/..',
            'pretty'           => true,
            'modules'          => [JsPhpizePhug::class],
            'compiler_options' => [
                'filters' => [
                    'custom' => function ($contents) {
                        return 'BEGIN'.$contents.'END';
                    },
                    'coffee-script' => function ($contents, $options) use ($uglify) {
                        $engine = new CoffeeScript($contents, false);
                        $result = $engine->getResult();
                        if (isset($options['minify']) && $options['minify']) {
                            $result = $uglify($result);
                            // @TODO fix it when https://github.com/pugjs/pug/issues/2829 answered
                            $result = '(function(){}).call(this);';
                        }

                        return "\n".$result."\n";
                    },
                    'less' => function ($contents) {
                        $engine = new Less($contents);

                        return "\n".$engine->getResult()."\n";
                    },
                    'markdown-it' => function ($contents) {
                        $engine = new Markdown();

                        return $engine->parse($contents);
                    },
                    'stylus' => function ($contents) {
                        $engine = new Stylus($contents);

                        return "\n".$engine->getCss()."\n";
                    },
                    'uglify-js' => function ($contents) use ($uglify) {
                        return "\n".$uglify($contents)."\n";
                    },
                    'minify' => function ($contents) use ($uglify) {
                        return "\n".$uglify($contents)."\n";
                    },
                    'verbatim' => function ($contents) {
                        return $contents;
                    },
                ],
            ],
        ]);
        $this->renderer->share('Object', [
            'create' => function () {
                return new stdClass();
            },
        ]);
    }

    public static function flatContent($content)
    {
        return implode('', array_map(function ($line) {
            $line = trim($line);
            $line = preg_replace_callback('/(\s+[a-z:_-]+="(?:\\\\[\\S\\s]|[^"\\\\])*"){2,}/', function ($matches) {
                $attributes = [];
                $input = $matches[0];
                while (mb_strlen($input) && preg_match('/^\s+[a-z:_-]+="(?:\\\\[\\S\\s]|[^"\\\\])*"/', $input, $match)) {
                    $attributes[] = trim($match[0]);
                    $input = mb_substr($input, mb_strlen($match[0]));
                }
                sort($attributes);

                return ' '.implode(' ', $attributes);
            }, $line);

            return $line;
        }, preg_split('/\r|\n/', self::standardLines($content))));
    }

    public static function standardLines($content)
    {
        $content = preg_replace('/\s*<!--\s*(\S[\s\S]*?\S)\s*-->/', '<!--$1-->', $content);

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
        $expected = self::standardLines($expected);
        $actual = self::standardLines($actual);

        if (is_callable($message)) {
            $message = $message();
        }

        self::assertSame($expected, $actual, $message);
    }
}
