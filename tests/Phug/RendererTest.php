<?php

namespace Phug\Test;

use JsPhpize\JsPhpizePhug;
use Phug\LexerException;
use Phug\Renderer;
use Phug\Renderer\Adapter\EvalAdapter;
use Phug\Renderer\Adapter\FileAdapter;
use Phug\Renderer\Adapter\StreamAdapter;
use Phug\RendererException;

/**
 * @coversDefaultClass \Phug\Renderer
 */
class RendererTest extends AbstractRendererTest
{
    /**
     * @covers ::compileString
     * @covers ::renderString
     */
    public function testRenderString()
    {
        $actual = trim($this->renderer->renderString('#{"p"}.foo Hello'));
        self::assertSame('<p class="foo">Hello</p>', $actual);
    }

    /**
     * @covers ::useFileMethod
     * @covers ::renderFile
     * @covers ::renderString
     * @covers ::render
     */
    public function testRender()
    {
        foreach ([true, false] as $fileAutoDetect) {
            $renderer = new Renderer([
                'file_auto_detect' => $fileAutoDetect,
            ]);
            $actual = trim($renderer->render('#{"p"}.foo Hello'));

            self::assertSame('<p class="foo">Hello</p>', $actual);
        }

        $renderer = new Renderer([
            'file_auto_detect' => true,
            'compiler_modules' => [JsPhpizePhug::class],
        ]);

        $actual = str_replace(
            ["\r", "\n"],
            '',
            trim($renderer->render(__DIR__.'/../cases/code.pug'))
        );
        $expected = str_replace(
            ["\r", "\n"],
            '',
            trim(file_get_contents(__DIR__.'/../cases/code.html'))
        );

        self::assertSame($expected, $actual);

        $renderer->setOption('file_auto_detect', false);
        $actual = str_replace(
            ["\r", "\n"],
            '',
            trim($renderer->render(__DIR__.'/../cases/code.pug'))
        );
        $expected = str_replace(
            ["\r", "\n"],
            '',
            trim($renderer->renderString(__DIR__.'/../cases/code.pug'))
        );

        self::assertSame($expected, $actual);
    }

    /**
     * @covers ::useFileMethod
     * @covers ::displayFile
     * @covers ::displayString
     * @covers ::display
     */
    public function testDisplay()
    {
        foreach ([true, false] as $fileAutoDetect) {
            $renderer = new Renderer([
                'file_auto_detect' => $fileAutoDetect,
            ]);
            ob_start();
            $renderer->display('#{"p"}.foo Hello');
            $actual = str_replace(
                "\r",
                '',
                trim(ob_get_contents())
            );
            ob_end_clean();

            self::assertSame('<p class="foo">Hello</p>', $actual);
        }

        $renderer = new Renderer([
            'file_auto_detect' => true,
            'compiler_modules' => [JsPhpizePhug::class],
        ]);

        ob_start();
        $renderer->display(__DIR__.'/../cases/code.pug');
        $actual = str_replace(
            ["\r", "\n"],
            '',
            trim(ob_get_contents())
        );
        ob_end_clean();
        $expected = str_replace(
            ["\r", "\n"],
            '',
            trim(file_get_contents(__DIR__.'/../cases/code.html'))
        );

        self::assertSame($expected, $actual);

        $renderer->setOption('file_auto_detect', false);

        ob_start();
        $renderer->display(__DIR__.'/../cases/code.pug');
        $actual = str_replace(
            ["\r", "\n"],
            '',
            trim(ob_get_contents())
        );
        ob_end_clean();
        ob_start();
        $renderer->displayString(__DIR__.'/../cases/code.pug');
        $expected = str_replace(
            ["\r", "\n"],
            '',
            trim(ob_get_contents())
        );
        ob_end_clean();

        self::assertSame($expected, $actual);
    }

    /**
     * @covers ::useFileMethod
     * @covers ::compileFile
     * @covers ::compileString
     * @covers ::compile
     */
    public function testCompile()
    {
        foreach ([true, false] as $fileAutoDetect) {
            $renderer = new Renderer([
                'debug'            => false,
                'file_auto_detect' => $fileAutoDetect,
            ]);
            $actual = trim($renderer->compile('p Hello'));

            self::assertSame('<p>Hello</p>', $actual);
        }

        $renderer = new Renderer([
            'pretty'           => true,
            'debug'            => false,
            'file_auto_detect' => true,
        ]);

        $actual = $renderer->compile(__DIR__.'/../cases/basic.pug');
        $actual = str_replace(
            "\r",
            '',
            trim($actual)
        );
        $expected = str_replace(
            "\r",
            '',
            trim(file_get_contents(__DIR__.'/../cases/basic.html'))
        );

        self::assertSame($expected, $actual);

        $renderer->setOption('file_auto_detect', false);
        $actual = $renderer->compile(__DIR__.'/../cases/basic.pug');
        $actual = str_replace(
            "\r",
            '',
            trim($actual)
        );
        $expected = str_replace(
            "\r",
            '',
            trim($renderer->compileString(__DIR__.'/../cases/basic.pug'))
        );

        self::assertSame($expected, $actual);
    }

    /**
     * @covers ::displayString
     */
    public function testDisplayString()
    {
        ob_start();
        $this->renderer->displayString('#{"p"}.foo Hello');
        $actual = trim(ob_get_contents());
        ob_end_clean();
        self::assertSame('<p class="foo">Hello</p>', $actual);
    }

    /**
     * @covers ::displayFile
     */
    public function testDisplayFile()
    {
        ob_start();
        $this->renderer->displayFile(__DIR__.'/../cases/code.pug');
        $actual = str_replace(
            "\r",
            '',
            trim(ob_get_contents())
        );
        ob_end_clean();
        $expected = str_replace(
            "\r",
            '',
            trim(file_get_contents(__DIR__.'/../cases/code.html'))
        );

        self::assertSame($expected, $actual);
    }

    /**
     * @covers ::__construct
     */
    public function testFilter()
    {
        $actual = str_replace(
            "\r",
            '',
            trim($this->renderer->renderString('script: :cdata foo'))
        );
        self::assertSame('<script><![CDATA[foo]]></script>', $actual);
    }

    /**
     * @covers ::__construct
     * @covers ::getCompiler
     * @covers ::handleOptionAliases
     */
    public function testBasedir()
    {
        $renderer = new Renderer([
            'basedir' => ['a'],
            'paths'   => ['b'],
        ]);
        $paths = $renderer->getCompiler()->getOption('paths');
        self::assertCount(2, $paths);
        self::assertContains('a', $paths);
        self::assertContains('b', $paths);
    }

    /**
     * @covers ::share
     * @covers ::resetSharedVariables
     * @covers ::mergeWithSharedVariables
     */
    public function testShare()
    {
        $this->renderer->share([
            'foo' => 1,
            'bar' => 2,
        ]);
        $this->renderer->share('foo', 4);

        $actual = str_replace(
            "\r",
            '',
            trim($this->renderer->renderString('=foo + bar'))
        );
        self::assertSame('6', $actual);

        $actual = str_replace(
            "\r",
            '',
            trim($this->renderer->renderString('=foo - bar'))
        );
        self::assertSame('2', $actual);

        $this->renderer->resetSharedVariables();
        $actual = str_replace(
            "\r",
            '',
            trim($this->renderer->renderString('=foo || bar ? "ok" : "ko"'))
        );
        self::assertSame('ko', $actual);
    }

    /**
     * @covers ::getAdapter
     * @covers ::getCompiler
     * @covers ::callAdapter
     * @covers \Phug\Renderer\AbstractAdapter::<public>
     * @covers \Phug\Renderer\Adapter\EvalAdapter::display
     */
    public function testOptions()
    {
        $this->renderer->setOption('pretty', true);

        $actual = str_replace(
            "\r",
            '',
            trim($this->renderer->renderString('section: div Hello'))
        );
        self::assertSame(
            "<section>\n".
            "  <div>Hello</div>\n".
            '</section>',
            $actual
        );

        $this->renderer->setOption('pretty', false);

        $actual = str_replace(
            "\r",
            '',
            trim($this->renderer->renderString('section: div Hello'))
        );
        self::assertSame(
            '<section>'.
            '<div>Hello</div>'.
            '</section>',
            $actual
        );

        $template = "p\n    i\n\tb";
        $actual = trim($this->renderer->renderString($template));
        self::assertSame('<p><i></i><b></b></p>', $actual);

        $this->renderer->setOptionsRecursive([
            'adapter_class_name' => FileAdapter::class,
            'allow_mixed_indent' => false,
        ]);
        $message = '';
        try {
            $this->renderer->renderString($template);
        } catch (LexerException $error) {
            $message = $error->getMessage();
        }

        self::assertSame(
            'Failed to lex: Invalid indentation, you can use tabs or spaces but not both'."\n".
            'Near: b'."\n".
            'Line: 3'."\n".
            'Offset: 2',
            trim(preg_replace('/\s*\n\s*/', "\n", $message))
        );
    }

    /**
     * @covers ::handleError
     * @covers ::callAdapter
     * @covers \Phug\Renderer\AbstractAdapter::captureBuffer
     */
    public function testHandleError()
    {
        foreach ([
            FileAdapter::class,
            EvalAdapter::class,
            StreamAdapter::class,
        ] as $adapter) {
            $renderer = new Renderer([
                'debug'              => false,
                'adapter_class_name' => $adapter,
            ]);
            $message = null;
            try {
                $renderer->renderString('div: p=12/0');
            } catch (\Exception $error) {
                $message = $error->getMessage();
            }

            self::assertContains(
                'Division by zero',
                $message
            );

            self::assertNotContains(
                '12/0',
                str_replace(' ', '', $message)
            );

            $message = null;
            $renderer->setOption('debug', true);
            try {
                $renderer->renderString('div: p=12/0');
            } catch (RendererException $error) {
                $message = $error->getMessage();
            }

            self::assertContains(
                'Division by zero on line 1',
                $message
            );

            self::assertContains(
                '12/0',
                str_replace(' ', '', $message)
            );
        }

        $message = null;
        $renderer = new Renderer([
            'debug'         => false,
            'pretty'        => true,
            'error_handler' => function ($error) use (&$message) {
                /* @var \Throwable $error */
                $message = $error->getMessage();
            },
        ]);
        $path = realpath(__DIR__.'/../utils/error.pug');
        $renderer->renderFile($path);

        self::assertContains(
            defined('HHVM_VERSION')
                ? 'Invalid operand type was used: implode() '.
                'expects a container as one of the arguments'
                : 'implode(): Invalid arguments passed',
            $message
        );

        self::assertNotContains(
            'on line 3',
            $message
        );

        self::assertNotContains(
            $path,
            $message
        );

        $message = null;
        $renderer->setOption('debug', true);
        $renderer->renderFile($path);

        self::assertContains(
            defined('HHVM_VERSION')
                ? 'Invalid operand type was used: implode() '.
                'expects a container as one of the arguments on line 3'
                : 'implode(): Invalid arguments passed on line 3',
            $message
        );

        self::assertContains(
            $path,
            $message
        );

        self::assertContains(
            "implode('','')",
            $message
        );
    }

    /**
     * @group error
     * @covers ::getErrorMessage
     * @covers ::highlightLine
     * @covers ::getRendererException
     * @covers ::hasColorSupport
     * @covers ::getDebuggedException
     * @covers ::handleError
     * @covers ::callAdapter
     * @covers \Phug\Renderer\AbstractAdapter::captureBuffer
     */
    public function testHandleParseError()
    {
        if (version_compare(PHP_VERSION, '7.0.0') < 0) {
            self::markTestSkipped('Parse error can only be caught since PHP 7.');

            return;
        }

        $renderer = new Renderer([
            'debug'              => true,
            'adapter_class_name' => FileAdapter::class,
        ]);

        $message = null;
        try {
            $renderer->renderString(
                "doctype html\n".
                "html\n".
                "  //-\n".
                "    Many\n".
                "    Boring\n".
                "    So\n".
                "    Boring\n".
                "    Comments\n".
                "  head\n".
                "    title Foo\n".
                "  body\n".
                "    section= MyClass:::error()\n".
                "    footer\n".
                "      | End\n".
                "  //-\n".
                "    Many\n".
                "    Boring\n".
                "    So\n".
                "    Boring\n".
                "    Comments\n".
                '  // Too far to be visible error context'
            );
        } catch (RendererException $error) {
            $message = $error->getMessage();
        }

        self::assertContains('ParseError:', $message);
        self::assertContains(' on line 12, offset 12', $message);
        self::assertContains('title Foo', $message);
        self::assertNotContains('Too far to be visible error context', $message);
    }
}
