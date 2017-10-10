<?php

namespace Phug\Renderer\Adapter;

use Phug\Renderer;
use Phug\Renderer\AbstractAdapter;
use Phug\Renderer\CacheInterface;
use Phug\Util\SandBox;
use RuntimeException;

class FileAdapter extends AbstractAdapter implements CacheInterface
{
    private $renderingFile;

    public function __construct(Renderer $renderer, $options)
    {
        parent::__construct($renderer, [
            'cache_dir'           => null,
            'tmp_dir'             => sys_get_temp_dir(),
            'tmp_name_function'   => 'tempnam',
            'up_to_date_check'    => true,
            'keep_base_name'      => false,
        ]);

        $this->setOptions($options);
    }

    /**
     * Return the cached file path after cache optional process.
     *
     * @param $path
     * @param string   $input    pug input
     * @param callable $rendered method to compile the source into PHP
     * @param bool     $success
     *
     * @return string
     */
    public function cache($path, $input, callable $rendered, &$success = null)
    {
        $cacheFolder = $this->getCacheDirectory();
        $destination = $path;

        if (!$this->isCacheUpToDate($destination, $input)) {
            if (!is_writable($cacheFolder)) {
                throw new RuntimeException(sprintf('Cache directory must be writable. "%s" is not.', $cacheFolder), 6);
            }

            $success = file_put_contents($destination, $rendered($path, $input));
        }

        return $destination;
    }

    /**
     * Display rendered template after optional cache process.
     *
     * @param $path
     * @param string   $input     pug input
     * @param callable $rendered  method to compile the source into PHP
     * @param array    $variables local variables
     * @param bool     $success
     */
    public function displayCached($path, $input, callable $rendered, array $variables, &$success = null)
    {
        $__pug_parameters = $variables;
        $__pug_path = $this->cache($path, $input, $rendered, $success);

        call_user_func(function () use ($__pug_path, $__pug_parameters) {
            extract($__pug_parameters);
            include $__pug_path;
        });
    }

    /**
     * Scan a directory recursively, compile them and save them into the cache directory.
     *
     * @param string $directory the directory to search in pug templates
     *
     * @return array count of cached files and error count
     */
    public function cacheDirectory($directory)
    {
        $success = 0;
        $errors = 0;
        $errorDetails = [];

        $extensions = $this->getRenderer()->getCompiler()->getOption('extensions');

        foreach (scandir($directory) as $object) {
            if ($object === '.' || $object === '..') {
                continue;
            }
            $inputFile = $directory.DIRECTORY_SEPARATOR.$object;
            if (is_dir($inputFile)) {
                list($subSuccess, $subErrors) = $this->cacheDirectory($inputFile);
                $success += $subSuccess;
                $errors += $subErrors;
                continue;
            }
            if ($this->fileMatchExtensions($object, $extensions)) {
                $path = $inputFile;
                $this->isCacheUpToDate($path);
                $sandBox = new SandBox(function () use (&$success, $path, $inputFile) {
                    file_put_contents($path, $this->getRenderer()->getCompiler()->compileFile($inputFile));
                    $success++;
                });

                if ($sandBox->getThrowable()) {
                    $errors++;
                    $errorDetails[] = [
                        'directory' => $directory,
                        'inputFile' => $inputFile,
                        'path'      => $path,
                        'error'     => $sandBox->getThrowable(),
                    ];
                }
            }
        }

        return [$success, $errors, $errorDetails];
    }

    protected function createTemporaryFile()
    {
        return call_user_func(
            $this->getOption('tmp_name_function'),
            $this->getOption('tmp_dir'),
            'pug'
        );
    }

    protected function getCompiledFile($php)
    {
        $this->renderingFile = $this->createTemporaryFile();
        file_put_contents($this->renderingFile, $php);

        return $this->renderingFile;
    }

    public function display($__pug_php, array $__pug_parameters)
    {
        extract($__pug_parameters);
        include $this->getCompiledFile($__pug_php);
    }

    public function getRenderingFile()
    {
        return $this->renderingFile;
    }

    /**
     * Return a file path in the cache for a given name.
     *
     * @param string $name
     *
     * @return string
     */
    private function getCachePath($name)
    {
        $cacheDir = $this->getCacheDirectory();

        return str_replace('//', '/', $cacheDir.'/'.$name).'.php';
    }

    /**
     * Return a hashed print from input file or content.
     *
     * @param string $input
     *
     * @return string
     */
    private function hashPrint($input)
    {
        // Get the stronger hashing algorithm available to minimize collision risks
        $algorithms = hash_algos();
        $algorithm = $algorithms[0];
        $number = 0;
        foreach ($algorithms as $hashAlgorithm) {
            if (strpos($hashAlgorithm, 'md') === 0) {
                $hashNumber = substr($hashAlgorithm, 2);
                if ($hashNumber > $number) {
                    $number = $hashNumber;
                    $algorithm = $hashAlgorithm;
                }
                continue;
            }
            if (strpos($hashAlgorithm, 'sha') === 0) {
                $hashNumber = substr($hashAlgorithm, 3);
                if ($hashNumber > $number) {
                    $number = $hashNumber;
                    $algorithm = $hashAlgorithm;
                }
                continue;
            }
        }

        return rtrim(strtr(base64_encode(hash($algorithm, $input, true)), '+/', '-_'), '=');
    }

    /**
     * Return true if the file or content is up to date in the cache folder,
     * false else.
     *
     * @param &string $path  to be filled
     * @param string  $input file or pug code
     *
     * @return bool
     */
    private function isCacheUpToDate(&$path, $input = null)
    {
        if (!$input) {
            $input = realpath($path);
            $path = $this->getCachePath(
                ($this->getOption('keep_base_name') ? basename($input) : '').
                $this->hashPrint($input)
            );

            // Do not re-parse file if original is older
            return !$this->getOption('up_to_date_check') ||
                (file_exists($path) && filemtime($input) < filemtime($path));
        }

        $path = $this->getCachePath($this->hashPrint($input));

        // Do not re-parse file if the same hash exists
        return file_exists($path);
    }

    private function getCacheDirectory()
    {
        $cacheFolder = $this->hasOption('cache_dir')
            ? $this->getOption('cache_dir')
            : null;
        if (!$cacheFolder && $cacheFolder !== false) {
            $cacheFolder = $this->getRenderer()->hasOption('cache_dir')
                ? $this->getRenderer()->getOption('cache_dir')
                : null;
        }
        if ($cacheFolder === true) {
            $cacheFolder = $this->getOption('tmp_dir');
        }

        if (!is_dir($cacheFolder) && !@mkdir($cacheFolder, 0777, true)) {
            throw new RuntimeException(
                $cacheFolder.': Cache directory doesn\'t exist.'."\n".
                'Create it with:'."\n".
                'mkdir -p '.escapeshellarg(realpath($cacheFolder))."\n".
                'Or replace your cache setting with a valid writable folder path.',
                5
            );
        }

        return $cacheFolder;
    }

    private function fileMatchExtensions($path, $extensions)
    {
        foreach ($extensions as $extension) {
            if (mb_substr($path, -mb_strlen($extension)) === $extension) {
                return true;
            }
        }

        return false;
    }
}
