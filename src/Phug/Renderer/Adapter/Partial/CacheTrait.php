<?php

namespace Phug\Renderer\Adapter\Partial;

use Exception;
use Phug\Renderer;
use RuntimeException;
use Throwable;

/**
 * Class CacheTrait.
 */
trait CacheTrait
{
    /**
     * @var Renderer
     */
    private $renderer;

    /**
     * @return Renderer
     */
    public function getRenderer()
    {
        return $this->renderer;
    }

    /**
     * @param Renderer $renderer
     */
    public function setRenderer(Renderer $renderer)
    {
        $this->renderer = $renderer;

        return $this;
    }

    /**
     * Return a file path in the cache for a given name.
     *
     * @param string $name
     *
     * @return string
     */
    protected function getCachePath($name)
    {
        return str_replace('//', '/', $this->getRenderer()->getOption('cache').'/'.$name).'.php';
    }

    /**
     * Return a hashed print from input file or content.
     *
     * @param string $input
     *
     * @return string
     */
    protected function hashPrint($input)
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
     * @param string  $input file or pug code
     * @param &string $path  to be filled
     *
     * @return bool
     */
    protected function isCacheUpToDate(&$path, $input = null)
    {
        if (!$input) {
            $input = realpath($path);
            $path = $this->getCachePath(
                ($this->getRenderer()->getOption('keep_base_name') ? basename($input) : '').
                $this->hashPrint($input)
            );

            // Do not re-parse file if original is older
            return !$this->getRenderer()->getOption('up_to_date_check') ||
                (file_exists($path) && filemtime($input) < filemtime($path));
        }

        $path = $this->getCachePath($this->hashPrint($input));

        // Do not re-parse file if the same hash exists
        return file_exists($path);
    }

    protected function getCacheDirectory()
    {
        $cacheFolder = $this->getRenderer()->getOption('cache');

        if (!is_dir($cacheFolder) && !@mkdir($cacheFolder, 0777, true)) {
            throw new RuntimeException(
                $cacheFolder.': Cache directory seem\'s to not exists'."\n".
                'Create it with:'."\n".
                'mkdir -p '.escapeshellarg(realpath($cacheFolder))."\n".
                'Or replace your cache setting with a valid writable folder path.',
                5
            );
        }

        return $cacheFolder;
    }

    protected function fileMatchExtensions($path, $extensions)
    {
        foreach ($extensions as $extension) {
            if (mb_substr($path, -mb_strlen($extension)) === $extension) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return the cached file path after cache optional process.
     *
     * @param string   $input    pug input
     * @param string   $input    pug input
     * @param callable $rendered method to compile the source into PHP
     * @param bool     $success
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     *
     * @return string
     */
    public function cache($path, $input, callable $rendered, &$success = null)
    {
        $cacheFolder = $this->getCacheDirectory();

        if (!$this->isCacheUpToDate($path, $input)) {
            if (!is_writable($cacheFolder)) {
                throw new RuntimeException(sprintf('Cache directory must be writable. "%s" is not.', $cacheFolder), 6);
            }

            $success = file_put_contents($path, $rendered($input));
        }

        return $path;
    }

    /**
     * Display rendered template after optional cache process.
     *
     * @param string   $input     pug input
     * @param string   $input     pug input
     * @param callable $rendered  method to compile the source into PHP
     * @param array    $variables local variables
     * @param bool     $success
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
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
                try {
                    file_put_contents($path, $this->getRenderer()->getCompiler()->compileFile($inputFile));
                    $success++;
                } catch (Throwable $e) { // PHP 7
                    $errors++;
                } catch (Exception $e) { // PHP 5
                    $errors++;
                }
            }
        }

        return [$success, $errors];
    }
}
