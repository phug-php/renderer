<?php

namespace Phug;

use Phug\Renderer\Adapter\EvalAdapter;
use Phug\Renderer\Adapter\FileAdapter;
use Phug\Renderer\AdapterInterface;
use Phug\Renderer\CacheInterface;
use Phug\Renderer\Event\HtmlEvent;
use Phug\Renderer\Event\RenderEvent;
use Phug\Renderer\Partial\Debug\DebuggerTrait;
use Phug\Renderer\Profiler\EventList;
use Phug\Renderer\Profiler\ProfilerModule;
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

        if ($onRender = $this->getOption('on_render')) {
            $this->attach(RendererEvent::RENDER, $onRender);
        }

        if ($onHtml = $this->getOption('on_html')) {
            $this->attach(RendererEvent::HTML, $onHtml);
        }

        $this->handleOptionAliases();

        $options = $this->getOptions();

        $compilerClassName = $this->getOption('compiler_class_name');

        if (!is_a($compilerClassName, CompilerInterface::class, true)) {
            throw new RendererException(
                "Passed compiler class $compilerClassName is ".
                'not a valid '.CompilerInterface::class
            );
        }

        $this->compiler = new $compilerClassName($options);

        $this->setOptionsDefaults([
            'memory_limit'       => $this->getOption('debug') ? -1 : 0x3200000, // 50MB by default in debug
            'execution_max_time' => $this->getOption('debug') ? -1 : 30000, // 30s by default in debug
        ]);

        if (!$this->getOption('enable_profiler') && $this->getOption('execution_max_time') > -1) {
            $this->setOptionsRecursive([
                'enable_profiler' => true,
                'profiler'        => [
                    'display'        => false,
                    'log'            => false,
                ],
            ]);
        }
        if ($this->getOption('enable_profiler')) {
            $this->setOptionsDefaults([
                'profiler' => [
                    'time_precision' => 3,
                    'line_height'    => 30,
                    'display'        => true,
                    'log'            => false,
                ],
            ]);
            $events = new EventList();
            $this->addModule(new ProfilerModule($events, $this));
            $this->compiler->addModule(new ProfilerModule($events, $this->compiler));
            $formatter = $this->compiler->getFormatter();
            $formatter->addModule(new ProfilerModule($events, $formatter));
            $parser = $this->compiler->getParser();
            $parser->addModule(new ProfilerModule($events, $parser));
            $lexer = $parser->getLexer();
            $lexer->addModule(new ProfilerModule($events, $lexer));
        }

        $adapterClassName = $this->getOption('adapter_class_name');

        if (!is_a($adapterClassName, AdapterInterface::class, true)) {
            throw new RendererException(
                "Passed adapter class $adapterClassName is ".
                'not a valid '.AdapterInterface::class
            );
        }
        $this->adapter = new $adapterClassName($this, $options);

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

    private function mergeWithSharedVariables(array $parameters)
    {
        return array_merge($this->getOption('shared_variables'), $parameters);
    }

    /**
     * @return AdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * @return Compiler
     */
    public function getCompiler()
    {
        return $this->compiler;
    }

    /**
     * @param array|string $variables
     * @param mixed        $value
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

    private function expectCacheAdapter($adapter)
    {
        if (!($adapter instanceof CacheInterface)) {
            throw new RendererException(
                'You cannot use "cache" option with '.get_class($adapter).
                ' because this adapter does not implement '.CacheInterface::class
            );
        }
    }

    /**
     * @param string   $method
     * @param string   $path
     * @param string   $input
     * @param callable $getSource
     * @param array    $parameters
     *
     * @throws RendererException
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
            $source = $getSource($path, $input);
            if ($this->hasOption('cache_dir') && $this->getOption('cache_dir')) {
                $this->expectCacheAdapter($adapter);
            }
            if ($adapter->hasOption('cache_dir') && $adapter->getOption('cache_dir')) {
                /* @var CacheInterface $adapter */
                $this->expectCacheAdapter($adapter);
                $display = function () use ($adapter, $path, $input, $getSource, $parameters) {
                    $adapter->displayCached($path, $input, $getSource, $parameters);
                };

                return in_array($method, ['display', 'displayFile'])
                    ? $display()
                    : $adapter->captureBuffer($display);
            }

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
     * @param string $string
     * @param string $filename
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
     * @param string $path
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
     * @param string $string     input string or path
     * @param array  $parameters parameters or file name
     * @param string $filename
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
     * @param string       $path
     * @param string|array $parameters parameters or file name
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
     * @param string $string     input string or path
     * @param array  $parameters parameters or file name
     * @param string $filename
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
     * @param string $path
     * @param array  $parameters
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
