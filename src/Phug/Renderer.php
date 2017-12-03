<?php

namespace Phug;

use Phug\Renderer\Adapter\EvalAdapter;
use Phug\Renderer\Adapter\FileAdapter;
use Phug\Renderer\AdapterInterface;
use Phug\Renderer\CacheInterface;
use Phug\Renderer\Event\HtmlEvent;
use Phug\Renderer\Event\RenderEvent;
use Phug\Renderer\Partial\Debug\DebuggerTrait;
use Phug\Util\ModuleContainerInterface;
use Phug\Util\Partial\ModuleContainerTrait;
use Phug\Util\SandBox;

class Renderer implements ModuleContainerInterface
{
    use ModuleContainerTrait;
    use DebuggerTrait;

    /**
     * @var Compiler
     */
    private $compiler;

    /**
     * @var AdapterInterface
     */
    private $adapter;

    /**
     * @var array
     */
    private $optionEvents = [];

    public function __construct($options = null)
    {
        $this->setOptionsDefaults($options ?: [], [
            'debug'                 => true,
            'enable_profiler'       => false,
            'up_to_date_check'      => true,
            'keep_base_name'        => false,
            'error_handler'         => null,
            'html_error'            => php_sapi_name() !== 'cli',
            'color_support'         => null,
            'error_context_lines'   => 7,
            'adapter_class_name'    => isset($options['cache_dir']) && $options['cache_dir']
                ? FileAdapter::class
                : EvalAdapter::class,
            'shared_variables'    => [],
            'globals'             => [],
            'modules'             => [],
            'compiler_class_name' => Compiler::class,
            'self'                => false,
            'on_render'           => null,
            'on_html'             => null,
            'filters'             => [
                'cdata' => function ($contents) {
                    return '<![CDATA['.trim($contents).']]>';
                },
            ],
        ]);

        $this->initCompiler();

        $this->initDebugOptions($this);

        $adapterClassName = $this->getOption('adapter_class_name');

        if (!is_a($adapterClassName, AdapterInterface::class, true)) {
            throw new RendererException(
                "Passed adapter class $adapterClassName is ".
                'not a valid '.AdapterInterface::class
            );
        }
        $this->adapter = new $adapterClassName($this, $this->getOptions());

        $this->addModules($this->getOption('modules'));
        foreach ($this->getStaticModules() as $moduleClassName) {
            $interfaces = class_implements($moduleClassName);
            if (in_array(CompilerModuleInterface::class, $interfaces) &&
                !$this->compiler->hasModule($moduleClassName)
            ) {
                $this->compiler->addModule($moduleClassName);
                $this->setOptionsRecursive([
                    'compiler_modules' => [$moduleClassName],
                ]);
            }
            if (in_array(FormatterModuleInterface::class, $interfaces) &&
                !$this->compiler->getFormatter()->hasModule($moduleClassName)
            ) {
                $this->compiler->getFormatter()->addModule($moduleClassName);
                $this->setOptionsRecursive([
                    'formatter_modules' => [$moduleClassName],
                ]);
            }
            if (in_array(ParserModuleInterface::class, $interfaces) &&
                !$this->compiler->getParser()->hasModule($moduleClassName)
            ) {
                $this->compiler->getParser()->addModule($moduleClassName);
                $this->setOptionsRecursive([
                    'parser_modules' => [$moduleClassName],
                ]);
            }
            if (in_array(LexerModuleInterface::class, $interfaces) &&
                !$this->compiler->getParser()->getLexer()->hasModule($moduleClassName)
            ) {
                $this->compiler->getParser()->getLexer()->addModule($moduleClassName);
                $this->setOptionsRecursive([
                    'lexer_modules' => [$moduleClassName],
                ]);
            }
        }
    }

    private function handleOptionAliases()
    {
        if ($this->hasOption('basedir')) {
            $basedir = $this->getOption('basedir');
            $this->setOption('paths', array_merge(
                $this->hasOption('paths')
                    ? (array) $this->getOption('paths')
                    : [],
                is_array($basedir)
                    ? $basedir
                    : [$basedir]
            ));
        }
    }

    private function expectCacheAdapter($adapter)
    {
        if (!($adapter instanceof CacheInterface)) {
            throw new RendererException(
                'You cannot use "cache_dir" option with '.get_class($adapter).
                ' because this adapter does not implement '.CacheInterface::class
            );
        }
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

    /**
     * Get all file matching extensions list recursively in a directory.
     *
     * @param $directory
     *
     * @return \Generator
     */
    public function scanDirectory($directory)
    {
        $extensions = $this->getOption('extensions');

        foreach (scandir($directory) as $object) {
            if ($object === '.' || $object === '..') {
                continue;
            }
            $inputFile = $directory.DIRECTORY_SEPARATOR.$object;
            if (is_dir($inputFile)) {
                foreach ($this->scanDirectory($inputFile) as $file) {
                    yield $file;
                }

                continue;
            }
            if ($this->fileMatchExtensions($object, $extensions)) {
                yield $inputFile;
            }
        }
    }

    /**
     * Returns merged globals, shared variables and locals.
     *
     * @param array $parameters
     *
     * @return array
     */
    protected function mergeWithSharedVariables(array $parameters)
    {
        return array_merge(
            $this->getOption('globals'),
            $this->getOption('shared_variables'),
            $parameters
        );
    }

    /**
     * Initialize/re-initialize the compiler. You should use it if you change initial options (for example: on_render
     * or on_html events, or the compiler_class_name).
     *
     * @throws RendererException
     */
    public function initCompiler()
    {
        if ($onRender = $this->getOption('on_render')) {
            if (isset($this->optionEvents['on_render'])) {
                $this->detach(RendererEvent::RENDER, $this->optionEvents['on_render']);
            }
            $this->attach(RendererEvent::RENDER, $onRender);
        }

        if ($onHtml = $this->getOption('on_html')) {
            if (isset($this->optionEvents['on_html'])) {
                $this->detach(RendererEvent::HTML, $this->optionEvents['on_html']);
            }
            $this->attach(RendererEvent::HTML, $onHtml);
        }

        $this->optionEvents = [
            'on_render' => $onRender,
            'on_html'   => $onHtml,
        ];

        $this->handleOptionAliases();

        $compilerClassName = $this->getOption('compiler_class_name');

        if (!is_a($compilerClassName, CompilerInterface::class, true)) {
            throw new RendererException(
                "Passed compiler class $compilerClassName is ".
                'not a valid '.CompilerInterface::class
            );
        }

        $this->compiler = new $compilerClassName($this->getOptions());
    }

    /**
     * Get the current adapter used (file, stream, eval or custom adapter provided).
     *
     * @return AdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * Get the current compiler in use. The compiler class name can be changed with compiler_class_name option and
     * is Phug\Compiler by default.
     *
     * @return CompilerInterface
     */
    public function getCompiler()
    {
        return $this->compiler;
    }

    /**
     * Share variables (local templates parameters) with all future templates rendered.
     *
     * @example $renderer->share('lang', 'fr')
     * @example $renderer->share(['title' => 'My blog', 'today' => new DateTime()])
     *
     * @param array|string $variables a variables name-value pairs or a single variable name
     * @param mixed        $value     the variable value if the first argument given is a string
     *
     * @return $this
     */
    public function share($variables, $value = null)
    {
        if (func_num_args() === 2) {
            $key = $variables;
            $variables = [];
            $variables[$key] = $value;
        }

        return $this->setOptionsRecursive([
            'shared_variables' => $variables,
        ]);
    }

    /**
     * Remove all previously set shared variables.
     */
    public function resetSharedVariables()
    {
        return $this->setOption('shared_variables', []);
    }

    /**
     * Call a method on the adapter (render, renderFile, display, displayFile, more methods can be available depending
     * on the adapter user).
     *
     * @param string   $method
     * @param string   $path
     * @param string   $input
     * @param callable $getSource
     * @param array    $parameters
     *
     * @throws RendererException|\Throwable
     *
     * @return bool|string|null
     */
    public function callAdapter($method, $path, $input, callable $getSource, array $parameters)
    {
        $source = '';

        $renderEvent = new RenderEvent($input, $path, $method, $parameters);
        $this->trigger($renderEvent);
        $input = $renderEvent->getInput();
        $path = $renderEvent->getPath();
        $method = $renderEvent->getMethod();
        $parameters = $renderEvent->getParameters();
        if ($self = $this->getOption('self')) {
            $self = $self === true ? 'self' : strval($self);
            $parameters = [
                $self => $parameters,
            ];
        }

        $sandBox = new SandBox(function () use (&$source, $method, $path, $input, $getSource, $parameters) {
            $adapter = $this->getAdapter();
            $cacheEnabled = (
                $adapter->hasOption('cache_dir') && $adapter->getOption('cache_dir') ||
                $this->hasOption('cache_dir') && $this->getOption('cache_dir')
            );
            if ($cacheEnabled) {
                /* @var CacheInterface $adapter */
                $this->expectCacheAdapter($adapter);
                $display = function () use ($adapter, $path, $input, $getSource, $parameters) {
                    $adapter->displayCached($path, $input, $getSource, $parameters);
                };

                return in_array($method, ['display', 'displayFile'])
                    ? $display()
                    : $adapter->captureBuffer($display);
            }

            $source = $getSource($path, $input);

            return $adapter->$method(
                $source,
                $this->mergeWithSharedVariables($parameters)
            );
        });

        $htmlEvent = new HtmlEvent(
            $renderEvent,
            $sandBox->getResult(),
            $sandBox->getBuffer(),
            $sandBox->getThrowable()
        );
        $this->trigger($htmlEvent);

        if ($error = $htmlEvent->getError()) {
            $source = $source ?: $getSource($path, $input);
            $this->handleError($error, 1, $path, $source, $parameters, [
                'debug'               => $this->getOption('debug'),
                'error_handler'       => $this->getOption('error_handler'),
                'html_error'          => $this->getOption('html_error'),
                'error_context_lines' => $this->getOption('error_context_lines'),
                'color_support'       => $this->getOption('color_support'),
            ]);
        }

        if ($buffer = $htmlEvent->getBuffer()) {
            echo $buffer;
        }

        return $htmlEvent->getResult();
    }

    /**
     * Compile a pug template string into a PHP string.
     *
     * @param string $string   pug input string
     * @param string $filename optional file path of the given template
     *
     * @return string
     */
    public function compile($string, $filename = null)
    {
        $compiler = $this->getCompiler();

        $this->setDebugString($string);
        $this->setDebugFile($filename);
        $this->setDebugFormatter($compiler->getFormatter());

        return $compiler->compile($string, $filename);
    }

    /**
     * Compile a pug template file into a PHP string.
     *
     * @param string $path pug input file
     *
     * @return string
     */
    public function compileFile($path)
    {
        $compiler = $this->getCompiler();

        $this->setDebugFile($path);
        $this->setDebugFormatter($compiler->getFormatter());

        return $compiler->compileFile($path);
    }

    /**
     * Render a pug template string into a HTML/XML string (or any tag templates if you use a custom format).
     *
     * @param string $string     pug input string
     * @param array  $parameters parameters (values for variables used in the template)
     * @param string $filename   optional file path of the given template
     *
     * @throws RendererException
     *
     * @return string
     */
    public function render($string, array $parameters = [], $filename = null)
    {
        return $this->callAdapter(
            'render',
            null,
            $string,
            function ($path, $input) use ($filename) {
                return $this->compile($input, $filename);
            },
            $parameters
        );
    }

    /**
     * Render a pug template file into a HTML/XML string (or any tag templates if you use a custom format).
     *
     * @param string       $path       pug input file
     * @param string|array $parameters parameters (values for variables used in the template)
     *
     * @throws RendererException
     *
     * @return string
     */
    public function renderFile($path, array $parameters = [])
    {
        return $this->callAdapter(
            'render',
            $path,
            null,
            function ($path) {
                return $this->compileFile($path);
            },
            $parameters
        );
    }

    /**
     * Render all pug template files in an input directory and output in an other or the same directory.
     * Return an array with success count and error count.
     *
     * @param string       $path        pug input directory containing pug files
     * @param string       $destination pug output directory (optional)
     * @param string       $extension   file extension (optional, .html by default)
     * @param string|array $parameters  parameters (values for variables used in the template) (optional)
     *
     * @return array
     */
    public function renderDirectory($path, $destination = null, $extension = '.html', array $parameters = [])
    {
        if (is_array($destination)) {
            $parameters = $destination;
            $destination = null;
        } elseif (is_array($extension)) {
            $parameters = $extension;
            $extension = '.html';
        }
        if (!$destination) {
            $destination = $path;
        }
        $path = realpath($path);
        $destination = realpath($destination);

        $success = 0;
        $errors = 0;
        if ($path && $destination) {
            $path = rtrim($path, '/\\');
            $destination = rtrim($destination, '/\\');
            $length = mb_strlen($path);
            foreach ($this->scanDirectory($path) as $file) {
                $relativeDirectory = trim(mb_substr(dirname($file), $length), '//\\');
                $filename = pathinfo($file, PATHINFO_FILENAME);
                $outputDirectory = $destination.DIRECTORY_SEPARATOR.$relativeDirectory;
                $counter = null;
                if (!file_exists($outputDirectory)) {
                    if (!@mkdir($outputDirectory, 0777, true)) {
                        $counter = 'errors';
                    }
                }
                if (!$counter) {
                    $outputFile = $outputDirectory.DIRECTORY_SEPARATOR.$filename.$extension;
                    $sandBox = new SandBox(function () use ($outputFile, $file, $parameters) {
                        return file_put_contents($outputFile, $this->renderFile($file, $parameters));
                    });
                    $counter = $sandBox->getResult() ? 'success' : 'errors';
                }
                $$counter++;
            }
        }

        return [$success, $errors];
    }

    /**
     * Display a pug template string into a HTML/XML string (or any tag templates if you use a custom format).
     *
     * @param string $string     pug input string
     * @param array  $parameters parameters or file name
     * @param string $filename
     *
     * @throws RendererException|\Throwable
     */
    public function display($string, array $parameters = [], $filename = null)
    {
        return $this->callAdapter(
            'display',
            null,
            $string,
            function ($path, $input) use ($filename) {
                return $this->compile($input, $filename);
            },
            $parameters
        );
    }

    /**
     * Display a pug template file into a HTML/XML string (or any tag templates if you use a custom format).
     *
     * @param string $path       pug input file
     * @param array  $parameters parameters (values for variables used in the template)
     *
     * @throws RendererException|\Throwable
     */
    public function displayFile($path, array $parameters = [])
    {
        return $this->callAdapter(
            'display',
            $path,
            null,
            function ($path) {
                return $this->compileFile($path);
            },
            $parameters
        );
    }

    /**
     * Cache all templates in a directory in the cache directory you specified with the cache_dir option.
     * You should call after deploying your application in production to avoid a slower page loading for the first
     * user.
     *
     * @param $directory
     *
     * @throws RendererException
     *
     * @return array
     */
    public function cacheDirectory($directory)
    {
        $adapter = $this->getAdapter();
        if (!($adapter instanceof CacheInterface)) {
            throw new RendererException(
                'You cannot cache a directory with '.get_class($adapter).
                ' because this adapter does not implement '.CacheInterface::class
            );
        }

        return $adapter->cacheDirectory($directory);
    }
}
