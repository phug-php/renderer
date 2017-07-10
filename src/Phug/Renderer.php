<?php

namespace Phug;

use Exception;
use Phug\Renderer\Adapter\EvalAdapter;
use Phug\Renderer\Adapter\FileAdapter;
use Phug\Renderer\AdapterInterface;
use Phug\Renderer\CacheInterface;
use Phug\Util\ModulesContainerInterface;
use Phug\Util\OptionInterface;
use Phug\Util\Partial\ModuleTrait;
use Phug\Util\Partial\OptionTrait;
use Phug\Util\PugFileLocationInterface;
use Throwable;

class Renderer implements ModulesContainerInterface, OptionInterface
{
    use ModuleTrait;
    use OptionTrait;

    /**
     * @var Compiler
     */
    protected $compiler;

    /**
     * @var string
     */
    protected $lastString;

    /**
     * @var string
     */
    protected $lastFile;

    /**
     * @var array
     */
    protected $modulesOptionsPaths = [
        CompilerModuleInterface::class  => [],
        FormatterModuleInterface::class => ['formatter_options'],
        ParserModuleInterface::class    => ['parser_options'],
        LexerModuleInterface::class     => ['parser_options', 'lexer_options'],
    ];

    public function __construct(array $options = [])
    {
        $this->setOptionsRecursive([
            'debug'               => true,
            'cache'               => null,
            'up_to_date_check'    => true,
            'keep_base_name'      => false,
            'error_handler'       => null,
            'html_error'          => php_sapi_name() !== 'cli',
            'error_context_lines' => 7,
            'renderer_adapter'    => isset($options['cache'])
                ? FileAdapter::class
                : EvalAdapter::class,
            'renderer_options'    => [],
            'shared_variables'    => [],
            'modules'             => [],
            'compiler_options'    => [
                'formatter_options' => [],
                'parser_options'    => [
                    'lexer_options' => [],
                ],
                'filters' => [
                    'cdata' => function ($contents) {
                        return '<![CDATA['.trim($contents).']]>';
                    },
                ],
            ],
        ], $options);

        $modules = $this->getOption('modules');

        foreach ($modules as &$module) {
            foreach ($this->modulesOptionsPaths as $interface => $optionPath) {
                if (is_subclass_of($module, $interface)) {
                    $module = false;

                    break;
                }
            }
        }

        $this->setExpectedModuleType(RendererModuleInterface::class);
        $this->addModules(array_filter($modules));
        $this->initializeCompiler();
    }

    public function initializeCompiler()
    {
        $this->compiler = new Compiler($this->getCompilerOptions());
    }

    protected function mergeOptions(&$options, array $input, $optionName)
    {
        if (isset($options[$optionName]) &&
            is_array($options[$optionName]) &&
            is_array($input[$optionName])
        ) {
            $options[$optionName] = array_merge(
                $options[$optionName],
                $input[$optionName]
            );

            return;
        }

        $options[$optionName] = $input[$optionName];
    }

    protected function getCompilerOptions()
    {
        $options = $this->getOptions();

        $compilerOptions = &$options['compiler_options'];

        foreach ([
            'debug',
            'dependencies_storage',
            'default_format',
            'formats',
            'inline_tags',
            'self_closing_tags',
            'pattern',
            'patterns',
            'attribute_assignments',
            'assignment_handlers',
            'pretty',
        ] as $optionName) {
            if (isset($options[$optionName])) {
                $this->mergeOptions(
                    $compilerOptions['formatter_options'],
                    $options,
                    $optionName
                );
            }
        }

        foreach ([
            'basedir',
            'debug',
            'extensions',
            'default_tag',
            'pre_compile',
            'post_compile',
            'filters',
            'parser_class_name',
            'parser_options',
            'formatter_class_name',
            'formatter_options',
            'mixins_storage_mode',
            'node_compilers',
        ] as $optionName) {
            if (isset($options[$optionName])) {
                $this->mergeOptions(
                    $compilerOptions,
                    $options,
                    $optionName
                );
            }
        }

        if (isset($options['lexer_options'])) {
            $compilerOptions['parser_options']['lexer_options'] = array_merge(
                $compilerOptions['parser_options']['lexer_options'],
                $options['lexer_options']
            );
        }

        $modules = $this->getOption('modules');

        foreach ($modules as &$module) {
            foreach ($this->modulesOptionsPaths as $interface => $optionPath) {
                if (is_subclass_of($module, $interface)) {
                    $optionPath[] = 'modules';
                    $base = &$compilerOptions;
                    foreach ($optionPath as $key) {
                        if (!isset($base[$key])) {
                            $base[$key] = [];
                        }

                        $base = &$base[$key];
                    }

                    $base[] = $module;

                    break;
                }
            }
        }

        return $compilerOptions;
    }

    protected function mergeWithSharedVariables(array $parameters)
    {
        return array_merge($this->getOption('shared_variables'), $parameters);
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

    /**
     * @return AdapterInterface
     */
    public function getAdapter()
    {
        $adapterClass = $this->getOption('renderer_adapter');

        return new $adapterClass($this->getOption('renderer_options'), $this);
    }

    /**
     * @return Compiler
     */
    public function getCompiler()
    {
        return $this->compiler;
    }

    protected function highlightLine($lineText, $colored, $offset)
    {
        if ($this->getOption('html_error')) {
            return '<span class="error-line">'.
                (is_null($offset)
                    ? $lineText
                    : mb_substr($lineText, 0, $offset + 7).
                    '<span class="error-offset">'.
                    mb_substr($lineText, $offset + 7, 1).
                    '</span>'.
                    mb_substr($lineText, $offset + 8)
                ).
                "</span>\n";
        }

        if (!$colored) {
            return "$lineText\n";
        }

        return "\033[43;30m".
            (is_null($offset)
                ? $lineText
                : mb_substr($lineText, 0, $offset + 7).
                "\033[43;31m".
                mb_substr($lineText, $offset + 7, 1).
                "\033[43;30m".
                mb_substr($lineText, $offset + 8)
            ).
            "\e[0m\n";
    }

    protected function getErrorMessage($error, $line, $offset, $source, $path, $colored, $parameters = null)
    {
        $source = explode("\n", rtrim($source));
        $errorType = get_class($error);
        $message = $errorType;
        if ($path) {
            $message .= ' in '.$path;
        }
        $message .= ":\n".$error->getMessage().' on line '.$line.
            (is_null($offset) ? '' : ', offset '.$offset)."\n\n";
        $contextLines = $this->getOption('error_context_lines');
        $code = '';
        $untilOffset = mb_substr($source[$line - 1], 0, $offset ?: 0) ?: '';
        $htmlError = $this->getOption('html_error');
        $start = null;
        foreach ($source as $index => $lineText) {
            if (abs($index + 1 - $line) > $contextLines) {
                continue;
            }
            if (is_null($start)) {
                $start = $index + 1;
            }
            $number = strval($index + 1);
            $markLine = $line - 1 === $index;
            if (!$htmlError) {
                $lineText = ($markLine ? '>' : ' ').
                    str_repeat(' ', 4 - mb_strlen($number)).$number.' | '.
                    $lineText;
            }
            if (!$markLine) {
                $code .= $lineText."\n";

                continue;
            }
            $code .= $this->highlightLine($lineText, $colored, $offset);
            if (!$htmlError && !is_null($offset)) {
                $code .= str_repeat('-', $offset + 7)."^\n";
            }
        }
        if ($htmlError) {
            static::cleanBuffers();
            try {
                $trace = '## '.$error->getFile().'('.$error->getLine().")\n".$error->getTraceAsString();
                (new static([
                    'debug'   => false,
                    'filters' => [
                        'no-php' => function ($text) {
                            return str_replace('<?', '<<?= "?" ?>', $text);
                        },
                    ],
                ]))->displayFile(__DIR__.'/../debug/index.pug', [
                    'title'       => $error->getMessage(),
                    'trace'       => $trace,
                    'start'       => $start,
                    'untilOffset' => htmlspecialchars($untilOffset),
                    'line'        => $line,
                    'offset'      => $offset,
                    'message'     => trim($message),
                    'code'        => $code,
                    'parameters'  => $parameters ? print_r($parameters, true) : ''
                ]);
            } catch (\Throwable $exception) {
                echo '<pre>'.$exception->getMessage()."\n\n".$exception->getTraceAsString().'</pre>';
            }

            exit(1);
        }

        return $message.$code;
    }

    /**
     * Handle error occurred in compiled PHP.
     *
     * @param \Throwable $error
     * @param int        $code
     * @param string     $path
     * @param string     $source
     * @param array      $parameters
     *
     * @throws RendererException
     */
    public function handleError($error, $code, $path, $source, $parameters = null)
    {
        /* @var Throwable $error */
        $offset = null;
        $exception = $error;
        if ($this->getOption('debug')) {
            $isLocatedError = $error instanceof PugFileLocationInterface;
            if ($isLocatedError && is_null($error->getPugLine())) {
                static::cleanBuffers();
                throw $error;
            }
            $pugError = $isLocatedError
                ? $error
                : $this->getCompiler()->getFormatter()->getDebugError(
                    $error,
                    $source,
                    $this->getAdapter()->getRenderingFile()
                );
            $line = $pugError->getPugLine();
            $offset = $pugError->getPugOffset();
            $sourcePath = $pugError->getPugFile() ?: $path;
            $source = $sourcePath ? file_get_contents($sourcePath) : $this->lastString;
            $colorSupport = DIRECTORY_SEPARATOR === '\\'
                ? false !== getenv('ANSICON') ||
                'ON' === getenv('ConEmuANSI') ||
                false !== getenv('BABUN_HOME')
                : (false !== getenv('BABUN_HOME')) ||
                function_exists('posix_isatty') &&
                @posix_isatty(STDOUT);
            $isPugError = $error instanceof PugFileLocationInterface;
            $message = $this->getErrorMessage(
                $error,
                $isPugError ? $error->getPugLine() : $line,
                $isPugError ? $error->getPugOffset() : $offset,
                $isPugError ? file_get_contents($error->getPugFile()) : $source,
                $isPugError ? $error->getPugFile() : $sourcePath,
                $colorSupport,
                $parameters
            );
            $exception = new RendererException($message, $code, $error);
        }

        $handler = $this->getOption('error_handler');
        if (!$handler) {
            static::cleanBuffers();
            throw $exception;
        }

        $handler($exception);
    }

    protected static function cleanBuffers()
    {
        while (count(ob_list_handlers())) {
            ob_end_clean();
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
        if ($this->getOption('debug')) {
            ob_start();
        }
        $render = false;
        $source = '';

        try {
            $adapter = $this->getAdapter();
            $source = $getSource();
            if ($this->getOption('cache')) {
                if (!($adapter instanceof CacheInterface)) {
                    static::cleanBuffers();
                    throw new RendererException(
                        'You cannot use "cache" option with '.get_class($adapter).
                        ' because this adapter does not implement '.CacheInterface::class
                    );
                }

                $display = function () use ($adapter, $path, $input, $getSource, $parameters) {
                    $adapter->displayCached($path, $input, $getSource, $parameters);
                };

                return in_array($method, ['display', 'displayString'])
                    ? $display()
                    : $adapter->captureBuffer($display);
            }

            $render = $adapter->$method(
                $source,
                $this->mergeWithSharedVariables($parameters)
            );
        } catch (Throwable $error) {
            $this->handleError($error, 1, $path, $source, $parameters);
        } catch (Exception $error) {
            $this->handleError($error, 2, $path, $source, $parameters);
        }
        if ($this->getOption('debug')) {
            ob_end_flush();
        }

        return $render;
    }

    /**
     * @param string $string   input string or path
     * @param string $filename
     *
     * @return string
     */
    public function compile($path)
    {
        $method = file_exists($path) ? 'compileFile' : 'compileString';

        return call_user_func_array([$this, $method], func_get_args());
    }

    /**
     * @param string $string
     * @param string $filename
     *
     * @return string
     */
    public function compileString($string, $filename)
    {
        $this->lastString = $string;
        $this->lastFile = $filename;

        return $this->getCompiler()->compile($string, $filename);
    }

    /**
     * @param string $path
     *
     * @return string
     */
    public function compileFile($path)
    {
        $this->lastFile = $path;

        return $this->getCompiler()->compileFile($path);
    }

    /**
     * @param string       $string     input string or path
     * @param string|array $parameters parameters or file name
     * @param string       $filename
     *
     * @return string
     */
    public function render($path)
    {
        $method = file_exists($path) ? 'renderFile' : 'renderString';

        return call_user_func_array([$this, $method], func_get_args());
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
            function () use ($path) {
                return $this->compileFile($path);
            },
            $parameters
        );
    }

    /**
     * @param string $string     input string or path
     * @param array  $parameters parameters or file name
     * @param string $filename
     *
     * @return string
     */
    public function renderString($string, array $parameters = [], $filename = null)
    {
        return $this->callAdapter(
            'render',
            null,
            $string,
            function () use ($string, $filename) {
                return $this->compile($string, $filename);
            },
            $parameters
        );
    }

    /**
     * @param string       $string     input string or path
     * @param string|array $parameters parameters or file name
     * @param string       $filename
     */
    public function display($path)
    {
        $method = file_exists($path) ? 'displayFile' : 'displayString';

        return call_user_func_array([$this, $method], func_get_args());
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
            function () use ($path) {
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
    public function displayString($string, array $parameters = [], $filename = null)
    {
        return $this->callAdapter(
            'display',
            null,
            $string,
            function () use ($string, $filename) {
                return $this->compile($string, $filename);
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
