<?php

namespace Phug\Test;

use Phug\LexerException;
use Phug\Renderer;
use Phug\Renderer\Adapter\FileAdapter;
use Phug\RendererException;

/**
 * @coversDefaultClass \Phug\Renderer
 */
class RendererTest extends AbstractRendererTest
{
    /**
     * @covers ::compile
     * @covers ::renderString
     */
    public function testRenderString()
    {
        $actual = trim($this->renderer->renderString('#{"p"}.foo Hello'));
        self::assertSame('<p class="foo">Hello</p>', $actual);
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
     * @covers ::display
     */
    public function testDisplay()
    {
        ob_start();
        $this->renderer->display(__DIR__.'/../cases/code.pug');
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
     * @covers ::share
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
    }

    /**
     * @covers ::getAdapter
     * @covers ::getCompiler
     * @covers ::getCompilerOptions
     * @covers ::callAdapter
     * @covers ::mergeOptions
     * @covers \Phug\Renderer\AbstractAdapter::<public>
     * @covers \Phug\Renderer\Adapter\EvalAdapter::display
     */
    public function testOptions()
    {
        $this->renderer->setOption(['compiler_options', 'formatter_options', 'pretty'], true);

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

        $this->renderer->setOption(['compiler_options', 'formatter_options', 'pretty'], false);

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
            'lexer_options'      => [
                'allow_mixed_indent' => false,
            ],
        ]);
        $this->renderer->initializeCompiler();
        $message = '';
        try {
            $this->renderer->renderString($template);
        } catch (LexerException $error) {
            $message = $error->getMessage();
        }

        self::assertSame(
            'Failed to lex: Invalid indentation, you can use tabs or spaces but not both'."\n".
            'Near: b'."\n\n".
            'Line: 3'."\n".
            'Offset: 2'."\n".
            'Position: 9'."\n",
            $message
        );
    }

    /**
     * @covers ::handleError
     * @covers ::callAdapter
     * @covers ::getCliErrorMessage
     * @covers \Phug\Renderer\AbstractAdapter::captureBuffer
     */
    public function testHandleError()
    {
        $renderer = new Renderer([
            'debug'              => false,
            'adapter_class_name' => FileAdapter::class,
        ]);
        ob_start();
        $message = null;
        try {
            $renderer->renderString('div: p=12/0');
        } catch (RendererException $error) {
            $message = $error->getMessage();
        }
        $contents = ob_get_contents();
        ob_end_clean();

        self::assertContains(
            'Division by zero on line 1',
            $message
        );

        self::assertContains(
            '12/0',
            str_replace(' ', '', $message)
        );

        self::assertSame('', $contents);

        $message = null;
        $renderer = new Renderer([
            'debug'         => false,
            'pretty'        => true,
            'error_handler' => function ($error) use (&$message) {
                $message = $error->getMessage();
            },
        ]);
        $path = realpath(__DIR__.'/../utils/error.pug');
        $renderer->render($path);

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
     * @covers ::handleError
     * @covers ::callAdapter
     * @covers ::getCliErrorMessage
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
        self::assertContains(' on line 12, offset 14', $message);
        self::assertContains('title Foo', $message);
        self::assertNotContains('Too far to be visible error context', $message);
    }
}
