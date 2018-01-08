<?php

namespace Phug\Test\Adapter;

use JsPhpize\JsPhpizePhug;
use Phug\Renderer;
use Phug\Renderer\Adapter\FileAdapter;
use Phug\Renderer\Adapter\StreamAdapter;
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
     * @covers \Phug\Renderer\Partial\AdapterTrait::getAdapter
     * @covers \Phug\Renderer\AbstractAdapter::getRenderer
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
        self::assertSame($renderer, $adapter->getRenderer());
        $path = $adapter->getRenderingFile();
        self::assertFileExists($path);
        self::assertSame('<p>Hello</p>', file_get_contents($path));
    }

    /**
     * @covers ::<public>
     * @covers ::getCachePath
     * @covers ::hashPrint
     * @covers ::isCacheUpToDate
     * @covers ::getCacheDirectory
     * @covers ::cacheFile
     * @covers \Phug\Renderer\AbstractAdapter::<public>
     * @covers \Phug\Renderer\Partial\AdapterTrait::getAdapter
     * @covers \Phug\Renderer\Partial\FileSystemTrait::fileMatchExtensions
     * @covers \Phug\Renderer\Partial\FileSystemTrait::scanDirectory
     */
    public function testCache()
    {
        $directory = sys_get_temp_dir().'/pug'.mt_rand(0, 99999999);
        static::emptyDirectory($directory);
        if (!file_exists($directory)) {
            mkdir($directory);
        }
        $renderer = new Renderer([
            'cache_dir' => $directory,
        ]);
        $path = $directory.DIRECTORY_SEPARATOR.'test.pug';
        file_put_contents($path, 'p=$message');

        self::assertSame('<p>Hi</p>', $renderer->renderFile($path, [
            'message' => 'Hi',
        ]));

        $renderer->getAdapter()->setOption('up_to_date_check', false);
        file_put_contents($path, 'div=$message');

        self::assertSame('<p>Ho</p>', $renderer->renderFile($path, [
            'message' => 'Ho',
        ]));

        $renderer->getAdapter()->setOption('up_to_date_check', true);
        $GLOBALS['debug'] = true;

        self::assertSame('<div>He</div>', $renderer->renderFile($path, [
            'message' => 'He',
        ]));

        $renderer->getAdapter()->setOption('cache_dir', null);

        self::assertSame('<div>Ha</div>', $renderer->renderFile($path, [
            'message' => 'Ha',
        ]));

        $renderer->getAdapter()->setOption('cache_dir', null);
        $renderer->setOption('cache_dir', null);

        self::assertSame('<div>He</div>', $renderer->renderFile($path, [
            'message' => 'He',
        ]));

        $renderer->getAdapter()->setOption('cache_dir', true);

        self::assertSame('<div>Hu</div>', $renderer->renderFile($path, [
            'message' => 'Hu',
        ]));

        /** @var FileAdapter $fileAdapter */
        $fileAdapter = $renderer->getAdapter();
        $path1 = $fileAdapter->cache(
            __DIR__.'/../../cases/attrs.pug',
            file_get_contents(__DIR__.'/../../cases/attrs.pug'),
            function ($path, $input) {
                return "$path\n$input";
            });
        $path2 = $fileAdapter->cache(
            __DIR__.'/../../cases/attrs-data.pug',
            file_get_contents(__DIR__.'/../../cases/attrs-data.pug'),
            function ($path, $input) {
                return "$path\n$input";
            });

        self::assertNotEquals($path1, $path2);

        if (file_exists($path1)) {
            unlink($path1);
        }
        if (file_exists($path2)) {
            unlink($path2);
        }

        static::emptyDirectory($directory);
        $directory = sys_get_temp_dir().'/pug'.mt_rand(0, 99999999);
        static::emptyDirectory($directory);
        if (!file_exists($directory)) {
            mkdir($directory);
        }
        $renderer = new Renderer([
            'debug'     => false,
            'paths'     => [__DIR__.'/../../cases'],
            'modules'   => [JsPhpizePhug::class],
            'cache_dir' => $directory,
        ]);
        $attrs = $renderer->renderFile('attrs.pug');
        $attrsData = $renderer->renderFile('attrs-data.pug');
        $attrsAgain = $renderer->renderFile('attrs.pug');
        $files = array_filter(scandir($directory), function ($item) {
            return mb_substr($item, 0, 1) !== '.' && pathinfo($item, PATHINFO_EXTENSION) !== 'txt';
        });
        static::emptyDirectory($directory);

        self::assertNotEquals($attrs, $attrsData);
        self::assertSame($attrs, $attrsAgain);
        self::assertCount(2, $files);
    }

    /**
     * @covers \Phug\Renderer\Adapter\FileAdapter::isCacheUpToDate
     * @covers \Phug\Renderer\Adapter\FileAdapter::hasExpiredImport
     */
    public function testCacheOnExtendsChange()
    {
        $cacheDirectory = sys_get_temp_dir().'/pug-cache-'.mt_rand(0, 99999999);
        $templateDirectory = sys_get_temp_dir().'/pug-tempaltes-'.mt_rand(0, 99999999);
        foreach ([$cacheDirectory, $templateDirectory] as $directory) {
            static::emptyDirectory($directory);
            if (!file_exists($directory)) {
                mkdir($directory);
            }
        }
        $renderer = new Renderer([
            'basedir'   => $templateDirectory,
            'cache_dir' => $cacheDirectory,
        ]);
        $layout = $templateDirectory.DIRECTORY_SEPARATOR.'base.pug';
        file_put_contents($layout, "p in base\nblock content");
        $child = $templateDirectory.DIRECTORY_SEPARATOR.'extends.pug';
        file_put_contents($child, "extends base\nblock content\n  p in child");
        $render = function ($path) use ($renderer) {
            ob_start();
            $renderer->displayFile($path);
            $html = ob_get_contents();
            ob_end_clean();

            return $html;
        };

        self::assertSame('<p>in base</p><p>in child</p>', $render($child));

        file_put_contents($layout, "p in base updated!\nblock content");
        touch($layout, time() - 3600);
        clearstatcache();

        self::assertSame('<p>in base updated!</p><p>in child</p>', $render($child));

        foreach (scandir($cacheDirectory) as $file) {
            if (substr($file, -22) === '.imports.serialize.txt') {
                unlink($cacheDirectory.DIRECTORY_SEPARATOR.$file);
            }
        }

        file_put_contents($layout, "p in base updated twice!\nblock content");
        touch($layout, time() - 3600);
        clearstatcache();

        self::assertSame('<p>in base updated twice!</p><p>in child</p>', $render($child));

        foreach ([$cacheDirectory, $templateDirectory] as $directory) {
            static::emptyDirectory($directory);
        }
    }

    /**
     * @covers ::<public>
     * @covers ::getCachePath
     * @covers ::hashPrint
     * @covers ::isCacheUpToDate
     * @covers ::getCacheDirectory
     * @covers ::cacheFile
     * @covers \Phug\Renderer\AbstractAdapter::<public>
     * @covers \Phug\Renderer\Partial\AdapterTrait::getAdapter
     * @covers \Phug\Renderer\Partial\FileSystemTrait::fileMatchExtensions
     * @covers \Phug\Renderer\Partial\FileSystemTrait::scanDirectory
     */
    public function testSharedVariablesWithCache()
    {
        $directory = sys_get_temp_dir().'/pug'.mt_rand(0, 99999999);
        static::emptyDirectory($directory);
        if (!file_exists($directory)) {
            mkdir($directory);
        }
        $renderer = new Renderer([
            'cache_dir' => $directory,
        ]);
        $path = $directory.DIRECTORY_SEPARATOR.'test.pug';
        file_put_contents($path, 'p=$message');

        $renderer->share('message', 'Hi');
        self::assertSame('<p>Hi</p>', $renderer->renderFile($path));

        $renderer->getAdapter()->setOption('up_to_date_check', false);
        file_put_contents($path, 'div=$message');

        $renderer->share('message', 'Ho');
        self::assertSame('<p>Ho</p>', $renderer->renderFile($path));

        $renderer->getAdapter()->setOption('up_to_date_check', true);
        $GLOBALS['debug'] = true;

        $renderer->share('message', 'He');
        self::assertSame('<div>He</div>', $renderer->renderFile($path));

        $renderer->getAdapter()->setOption('cache_dir', null);

        $renderer->share('message', 'Ha');
        self::assertSame('<div>Ha</div>', $renderer->renderFile($path));

        $renderer->getAdapter()->setOption('cache_dir', null);
        $renderer->setOption('cache_dir', null);

        $renderer->share('message', 'He');
        self::assertSame('<div>He</div>', $renderer->renderFile($path));

        $renderer->getAdapter()->setOption('cache_dir', true);

        $renderer->share('message', 'Hu');
        self::assertSame('<div>Hu</div>', $renderer->renderFile($path));

        /** @var FileAdapter $fileAdapter */
        $fileAdapter = $renderer->getAdapter();
        $path1 = $fileAdapter->cache(
            __DIR__.'/../../cases/attrs.pug',
            file_get_contents(__DIR__.'/../../cases/attrs.pug'),
            function ($path, $input) {
                return "$path\n$input";
            });
        $path2 = $fileAdapter->cache(
            __DIR__.'/../../cases/attrs-data.pug',
            file_get_contents(__DIR__.'/../../cases/attrs-data.pug'),
            function ($path, $input) {
                return "$path\n$input";
            });

        self::assertNotEquals($path1, $path2);

        if (file_exists($path1)) {
            unlink($path1);
        }
        if (file_exists($path2)) {
            unlink($path2);
        }

        static::emptyDirectory($directory);
        $directory = sys_get_temp_dir().'/pug'.mt_rand(0, 99999999);
        static::emptyDirectory($directory);
        if (!file_exists($directory)) {
            mkdir($directory);
        }
        $renderer = new Renderer([
            'debug'     => false,
            'paths'     => [__DIR__.'/../../cases'],
            'modules'   => [JsPhpizePhug::class],
            'cache_dir' => $directory,
        ]);
        $attrs = $renderer->renderFile('attrs.pug');
        $attrsData = $renderer->renderFile('attrs-data.pug');
        $attrsAgain = $renderer->renderFile('attrs.pug');
        $files = array_filter(scandir($directory), function ($item) {
            return mb_substr($item, 0, 1) !== '.' && pathinfo($item, PATHINFO_EXTENSION) !== 'txt';
        });
        static::emptyDirectory($directory);

        self::assertNotEquals($attrs, $attrsData);
        self::assertSame($attrs, $attrsAgain);
        self::assertCount(2, $files);
    }

    /**
     * @covers \Phug\Renderer\Adapter\FileAdapter::isCacheUpToDate
     * @covers \Phug\Renderer\Adapter\FileAdapter::hasExpiredImport
     */
    public function testCacheOnImportsChange()
    {
        $directory = sys_get_temp_dir().'/pug'.mt_rand(0, 99999999);
        static::emptyDirectory($directory);
        if (!file_exists($directory)) {
            mkdir($directory);
        }
        $renderer = new Renderer([
            'cache_dir' => $directory,
        ]);
        $include = $directory.DIRECTORY_SEPARATOR.'test.pug';
        file_put_contents($include, 'p=$message');
        $path = $directory.DIRECTORY_SEPARATOR.'include.pug';
        file_put_contents($path, 'include test');

        self::assertSame('<p>Hi</p>', $renderer->renderFile($path, [
            'message' => 'Hi',
        ]));

        file_put_contents($include, 'div=$message');
        touch($include, time() - 3600);
        touch($path, time() - 3600);
        clearstatcache();

        $html = $renderer->renderFile($path, [
            'message' => 'Ho',
        ]);
        self::assertSame('<p>Ho</p>', $html);

        touch($include, time() + 3600);
        clearstatcache();

        self::assertSame('<div>Ha</div>', $renderer->renderFile($path, [
            'message' => 'Ha',
        ]));

        file_put_contents($include, 'p=$message');
        touch($include, time() - 3600);
        clearstatcache();

        foreach (scandir($directory) as $file) {
            if (substr($file, -22) === '.imports.serialize.txt') {
                unlink($directory.DIRECTORY_SEPARATOR.$file);
            }
        }

        self::assertSame('<p>He</p>', $renderer->renderFile($path, [
            'message' => 'He',
        ]));

        static::emptyDirectory($directory);
    }

    /**
     * @covers ::<public>
     * @covers ::getCachePath
     * @covers ::hashPrint
     * @covers ::isCacheUpToDate
     * @covers ::getCacheDirectory
     * @covers ::cacheFile
     * @covers \Phug\Renderer\AbstractAdapter::<public>
     * @covers \Phug\Renderer\Partial\FileSystemTrait::fileMatchExtensions
     * @covers \Phug\Renderer\Partial\FileSystemTrait::scanDirectory
     * @covers \Phug\Renderer\Partial\AdapterTrait::getSandboxCall
     * @covers \Phug\Renderer\Partial\AdapterTrait::handleHtmlEvent
     * @covers \Phug\Renderer\Partial\AdapterTrait::callAdapter
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
     * @covers \Phug\Renderer::cacheDirectory
     * @covers \Phug\Renderer\Partial\AdapterTrait::expectCacheAdapter
     * @covers \Phug\Renderer\Partial\AdapterTrait::getAdapter
     * @covers \Phug\Renderer\Partial\AdapterTrait::getSandboxCall
     * @covers \Phug\Renderer\Partial\AdapterTrait::handleHtmlEvent
     * @covers \Phug\Renderer\Partial\AdapterTrait::callAdapter
     * @covers \Phug\Renderer\Partial\FileSystemTrait::scanDirectory
     */
    public function testCacheIncompatibility()
    {
        $renderer = new Renderer([
            'adapter_class_name' => StreamAdapter::class,
            'cache_dir'          => sys_get_temp_dir(),
        ]);

        $renderer->render('foo');

        self::assertInstanceOf(FileAdapter::class, $renderer->getAdapter());
        self::assertSame(FileAdapter::class, $renderer->getOption('adapter_class_name'));

        $renderer = new Renderer([
            'adapter_class_name' => StreamAdapter::class,
            'cache_dir'          => sys_get_temp_dir(),
        ]);

        $emptyDirectory = sys_get_temp_dir().'/d'.mt_rand(0, 9999999);
        @mkdir($emptyDirectory);
        $renderer->cacheDirectory($emptyDirectory);
        @rmdir($emptyDirectory);

        self::assertInstanceOf(FileAdapter::class, $renderer->getAdapter());
        self::assertSame(FileAdapter::class, $renderer->getOption('adapter_class_name'));
    }

    /**
     * @covers                \Phug\Renderer\Partial\FileSystemTrait::scanDirectory
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
     * @covers                \Phug\Renderer\Partial\FileSystemTrait::scanDirectory
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
     * @covers \Phug\Renderer\Partial\FileSystemTrait::scanDirectory
     * @covers \Phug\Renderer::cacheDirectory
     * @covers \Phug\Renderer\Partial\RendererOptionsTrait::handleOptionAliases
     * @covers \Phug\Renderer\Partial\FileSystemTrait::fileMatchExtensions
     * @covers \Phug\Renderer\Adapter\FileAdapter::cacheFile
     * @covers \Phug\Renderer\Adapter\FileAdapter::cacheDirectory
     */
    public function testCacheDirectory()
    {
        $cacheDirectory = sys_get_temp_dir().'/pug-test';
        static::emptyDirectory($cacheDirectory);
        if (!is_dir($cacheDirectory)) {
            mkdir($cacheDirectory, 0777, true);
        }
        $templatesDirectory = __DIR__.'/../../utils';
        $renderer = new Renderer([
            'basedir'   => $templatesDirectory,
            'cache_dir' => $cacheDirectory,
        ]);
        list($success, $errors, $errorDetails) = $renderer->cacheDirectory($templatesDirectory);
        $filesCount = count(array_filter(scandir($cacheDirectory), function ($file) {
            return $file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) !== 'txt';
        }));
        $expectedCount = count(array_filter(array_merge(
            scandir($templatesDirectory),
            scandir($templatesDirectory.'/subdirectory'),
            scandir($templatesDirectory.'/subdirectory/subsubdirectory')
        ), function ($file) {
            return pathinfo($file, PATHINFO_EXTENSION) === 'pug';
        }));
        list($errSuccess, $errErrors, $errErrorDetails) = $renderer->cacheDirectory(__DIR__.'/../../errored');
        static::emptyDirectory($cacheDirectory);
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
        self::assertCount($errErrors, $errErrorDetails, 'Each error should match a detailed message.');

        self::assertSame(0, $errSuccess);
        self::assertSame(2, $errErrors);
        self::assertStringEndsWith('errored', $errErrorDetails[0]['directory']);
        self::assertStringEndsWith('errored.pug', $errErrorDetails[0]['inputFile']);
        self::assertContains(
            'Inconsistent indentation. Expecting either 0 or 4 spaces/tabs.',
            $errErrorDetails[0]['error']->getMessage()
        );
    }
}
