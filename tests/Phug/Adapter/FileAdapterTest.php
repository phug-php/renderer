<?php

namespace Phug\Test\Adapter;

use Phug\Renderer;
use Phug\Renderer\Adapter\FileAdapter;
use Phug\Renderer\Adapter\StreamAdapter;
use Phug\Renderer\CacheInterface;
use Phug\RendererException;
use Phug\Test\AbstractRendererTest;

/**
 * @coversDefaultClass \Phug\Renderer\Adapter\FileAdapter
 */
class FileAdapterTest extends AbstractRendererTest
{
    /**
     * @covers ::<public>
     * @covers ::createTemporaryFile
     * @covers ::getCompiledFile
     */
    public function testRender()
    {
        $renderer = new Renderer([
            'renderer_adapter' => FileAdapter::class,
        ]);

        self::assertSame('<p>Hello</p>', $renderer->renderString('p=$message', [
            'message' => 'Hello',
        ]));
    }

    /**
     * @covers ::<public>
     * @covers \Phug\Renderer\Adapter\Partial\CacheTrait::getRenderer
     * @covers \Phug\Renderer\Adapter\Partial\CacheTrait::setRenderer
     * @covers \Phug\Renderer\Adapter\Partial\CacheTrait::getCachePath
     * @covers \Phug\Renderer\Adapter\Partial\CacheTrait::hashPrint
     * @covers \Phug\Renderer\Adapter\Partial\CacheTrait::isCacheUpToDate
     * @covers \Phug\Renderer\Adapter\Partial\CacheTrait::getCacheDirectory
     * @covers \Phug\Renderer\Adapter\Partial\CacheTrait::fileMatchExtensions
     * @covers \Phug\Renderer\Adapter\Partial\CacheTrait::displayCached
     * @covers \Phug\Renderer\Adapter\Partial\CacheTrait::cacheDirectory
     * @covers \Phug\Renderer\AbstractAdapter::<public>
     * @covers \Phug\Renderer::callAdapter
     */
    public function testCache()
    {
        $renderer = new Renderer([
            'cache' => sys_get_temp_dir(),
        ]);
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test.pug';
        file_put_contents($path, 'p=$message');

        self::assertSame('<p>Hi</p>', $renderer->render($path, [
            'message' => 'Hi',
        ]));

        $renderer->setOption('up_to_date_check', false);
        file_put_contents($path, 'div=$message');

        self::assertSame('<p>Hi</p>', $renderer->render($path, [
            'message' => 'Hi',
        ]));

        $renderer->setOption('up_to_date_check', true);

        self::assertSame('<div>Hi</div>', $renderer->render($path, [
            'message' => 'Hi',
        ]));

        ob_start();
        $renderer->displayString('section=$message', [
            'message' => 'Hi',
        ]);
        $actual = str_replace(
            "\r",
            '',
            trim(ob_get_contents())
        );
        ob_end_clean();

        self::assertSame('<section>Hi</section>', $actual);
    }

    /**
     * @covers \Phug\Renderer::callAdapter
     * @covers \Phug\Renderer::cacheDirectory
     */
    public function testCacheIncompatibility()
    {
        $message = null;
        $renderer = new Renderer([
            'renderer_adapter' => StreamAdapter::class,
            'cache'            => sys_get_temp_dir(),
        ]);
        try {
            $renderer->render('foo');
        } catch (RendererException $error) {
            $message = $error->getMessage();
        }

        self::assertSame('You cannot use "cache" option with '.StreamAdapter::class.
            ' because this adapter does not implement '.CacheInterface::class,
            $message
        );

        $message = null;
        try {
            $renderer->cacheDirectory('foo');
        } catch (RendererException $error) {
            $message = $error->getMessage();
        }

        self::assertSame('You cannot cache a directory with '.StreamAdapter::class.
            ' because this adapter does not implement '.CacheInterface::class,
            $message
        );
    }

    protected function emptyDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $file) {
            if ($file !== '.' && $file !== '..') {
                $path = $dir.'/'.$file;
                if (is_dir($path)) {
                    $this->emptyDirectory($path);
                } else {
                    unlink($path);
                }
            }
        }
    }

    /**
     * @covers                \Phug\Renderer::cacheDirectory
     * @covers                \Phug\Renderer\Adapter\Partial\CacheTrait::cacheDirectory
     * @covers                \Phug\Renderer\Adapter\Partial\CacheTrait::getCacheDirectory
     * @expectedException     \RuntimeException
     * @expectedExceptionCode 5
     */
    public function testMissingDirectory()
    {
        $renderer = new Renderer([
            'cache' => '///cannot/be/created',
        ]);
        $renderer->render(__DIR__.'/../../cases/attrs.pug');
    }

    /**
     * @covers                \Phug\Renderer::cacheDirectory
     * @covers                \Phug\Renderer\Adapter\Partial\CacheTrait::cacheDirectory
     * @covers                \Phug\Renderer\Adapter\Partial\CacheTrait::displayCached
     * @expectedException     \RuntimeException
     * @expectedExceptionCode 6
     */
    public function testReadOnlyDirectory()
    {
        $dir = __DIR__;
        while (is_writable($dir)) {
            $parent = realpath($dir.'/..');
            if ($parent === $dir) {
                $dir = 'C:';
                if (!file_exists($dir) || is_writable($dir)) {
                    self::markTestSkipped('No read-only directory found to do the test');

                    return;
                }
                break;
            }
            $dir = $parent;
        }
        $renderer = new Renderer([
            'cache' => $dir,
        ]);
        $renderer->render(__DIR__.'/../../cases/attrs.pug');
    }

    /**
     * @covers \Phug\Renderer::cacheDirectory
     * @covers \Phug\Renderer\Adapter\Partial\CacheTrait::cacheDirectory
     * @covers \Phug\Renderer\Adapter\Partial\CacheTrait::fileMatchExtensions
     */
    public function testCacheDirectory()
    {
        $cacheDirectory = sys_get_temp_dir().'/pug-test';
        $this->emptyDirectory($cacheDirectory);
        if (!is_dir($cacheDirectory)) {
            mkdir($cacheDirectory, 0777, true);
        }
        $templatesDirectory = __DIR__.'/../../utils';
        $renderer = new Renderer([
            'basedir' => $templatesDirectory,
            'cache'   => $cacheDirectory,
        ]);
        list($success, $errors) = $renderer->cacheDirectory($templatesDirectory);
        $filesCount = count(array_filter(scandir($cacheDirectory), function ($file) {
            return $file !== '.' && $file !== '..';
        }));
        $expectedCount = count(array_filter(array_merge(
            scandir($templatesDirectory),
            scandir($templatesDirectory.'/subdirectory'),
            scandir($templatesDirectory.'/subdirectory/subsubdirectory')
        ), function ($file) {
            return pathinfo($file, PATHINFO_EXTENSION) === 'pug';
        }));
        $this->emptyDirectory($cacheDirectory);
        rmdir($cacheDirectory);

        self::assertSame(
            $expectedCount,
            $success + $errors,
            'Each .pug file in the directory to cache should generate a success or an error.'
        );
        self::assertSame(
            $success,
            $filesCount,
            'Each file successfully cached should be in the cache directory.'
        );
    }
}
