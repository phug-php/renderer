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
     * @covers ::getRenderingFile
     * @covers \Phug\Renderer::getAdapter
     */
    public function testRender()
    {
        $renderer = new Renderer([
            'debug'              => false,
            'adapter_class_name' => FileAdapter::class,
        ]);

        self::assertSame('<p>Hello</p>', $renderer->render('p=$message', [
            'message' => 'Hello',
        ]));

        $renderer->render('p Hello');
        /** @var FileAdapter $adapter */
        $adapter = $renderer->getAdapter();

        self::assertInstanceOf(FileAdapter::class, $adapter);
        $path = $adapter->getRenderingFile();
        self::assertFileExists($path);
        self::assertSame('<p>Hello</p>', file_get_contents($path));
    }

    /**
     * @covers ::<public>
     * @covers \Phug\Renderer\Adapter\FileAdapter::getRenderer
     * @covers \Phug\Renderer\Adapter\FileAdapter::getCachePath
     * @covers \Phug\Renderer\Adapter\FileAdapter::hashPrint
     * @covers \Phug\Renderer\Adapter\FileAdapter::isCacheUpToDate
     * @covers \Phug\Renderer\Adapter\FileAdapter::getCacheDirectory
     * @covers \Phug\Renderer\Adapter\FileAdapter::fileMatchExtensions
     * @covers \Phug\Renderer\Adapter\FileAdapter::cache
     * @covers \Phug\Renderer\Adapter\FileAdapter::displayCached
     * @covers \Phug\Renderer\Adapter\FileAdapter::cacheDirectory
     * @covers \Phug\Renderer\AbstractAdapter::<public>
     * @covers \Phug\Renderer::callAdapter
     */
    public function testCache()
    {
        $renderer = new Renderer([
            'cache_dir' => sys_get_temp_dir(),
        ]);
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test.pug';
        file_put_contents($path, 'p=$message');

        self::assertSame('<p>Hi</p>', $renderer->renderFile($path, [
            'message' => 'Hi',
        ]));

        $renderer->getAdapter()->setOption('modified_check', false);
        file_put_contents($path, 'div=$message');

        self::assertSame('<p>Hi</p>', $renderer->renderFile($path, [
            'message' => 'Hi',
        ]));

        $renderer->getAdapter()->setOption('modified_check', true);

        self::assertSame('<div>Hi</div>', $renderer->renderFile($path, [
            'message' => 'Hi',
        ]));
    }

    /**
     * @covers ::<public>
     * @covers \Phug\Renderer\Adapter\FileAdapter::getRenderer
     * @covers \Phug\Renderer\Adapter\FileAdapter::getCachePath
     * @covers \Phug\Renderer\Adapter\FileAdapter::hashPrint
     * @covers \Phug\Renderer\Adapter\FileAdapter::isCacheUpToDate
     * @covers \Phug\Renderer\Adapter\FileAdapter::getCacheDirectory
     * @covers \Phug\Renderer\Adapter\FileAdapter::fileMatchExtensions
     * @covers \Phug\Renderer\Adapter\FileAdapter::cache
     * @covers \Phug\Renderer\Adapter\FileAdapter::displayCached
     * @covers \Phug\Renderer\Adapter\FileAdapter::cacheDirectory
     * @covers \Phug\Renderer\AbstractAdapter::<public>
     * @covers \Phug\Renderer::callAdapter
     */
    public function testCacheWithDisplay()
    {
        $renderer = new Renderer([
            'cache_dir' => sys_get_temp_dir(),
        ]);
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'test.pug';
        file_put_contents($path, 'p=$message');

        ob_start();
        $renderer->display('section=$message', [
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
     * @covers \Phug\Renderer::expectCacheAdapter
     * @covers \Phug\Renderer::callAdapter
     * @covers \Phug\Renderer::cacheDirectory
     */
    public function testCacheIncompatibility()
    {
        $message = null;
        $renderer = new Renderer([
            'adapter_class_name' => StreamAdapter::class,
            'cache_dir'          => sys_get_temp_dir(),
        ]);
        try {
            $renderer->render('foo');
        } catch (RendererException $error) {
            $message = $error->getMessage();
        }

        self::assertSame(
            'You cannot use "cache" option with '.StreamAdapter::class.
            ' because this adapter does not implement '.CacheInterface::class,
            $message
        );

        $message = null;
        try {
            $renderer->cacheDirectory('foo');
        } catch (RendererException $error) {
            $message = $error->getMessage();
        }

        self::assertSame(
            'You cannot cache a directory with '.StreamAdapter::class.
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
     * @covers                \Phug\Renderer\Adapter\FileAdapter::cacheDirectory
     * @covers                \Phug\Renderer\Adapter\FileAdapter::getCacheDirectory
     * @expectedException     \RuntimeException
     * @expectedExceptionCode 5
     */
    public function testMissingDirectory()
    {
        $renderer = new Renderer([
            'cache_dir' => '///cannot/be/created',
        ]);
        $renderer->render(__DIR__.'/../../cases/attrs.pug');
    }

    /**
     * @covers                \Phug\Renderer::cacheDirectory
     * @covers                \Phug\Renderer\Adapter\FileAdapter::cacheDirectory
     * @covers                \Phug\Renderer\Adapter\FileAdapter::cache
     * @covers                \Phug\Renderer\Adapter\FileAdapter::displayCached
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
            'cache_dir' => $dir,
        ]);
        $renderer->render(__DIR__.'/../../cases/attrs.pug');
    }

    /**
     * @covers \Phug\Renderer::handleOptionAliases
     * @covers \Phug\Renderer::cacheDirectory
     * @covers \Phug\Renderer\Adapter\FileAdapter::cacheDirectory
     * @covers \Phug\Renderer\Adapter\FileAdapter::fileMatchExtensions
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
            'basedir'   => $templatesDirectory,
            'cache_dir' => $cacheDirectory,
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
