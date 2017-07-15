<?php

namespace Phug;

use Exception;
use Phug\Renderer\Adapter\EvalAdapter;
use Phug\Renderer\Adapter\FileAdapter;
use Phug\Renderer\AdapterInterface;
use Phug\Renderer\CacheInterface;
use Phug\Util\Exception\LocatedException;
use Phug\Util\ModuleContainerInterface;
use Phug\Util\Partial\ModuleContainerTrait;
use Throwable;

class Renderer implements ModuleContainerInterface
{
    use ModuleContainerTrait;

    /**
     * @var Compiler
     */
    private $compiler;

    /**
     * @var AdapterInterface
     */
    private $adapter;

    /**
     * @var string
     */
    private $lastString;

    /**
     * @var string
     */
    private $lastFile;

    public function __construct(array $options = null)
    {
        $this->setOptionsRecursive([
            'debug'               => true,
            'error_handler'       => null,
            'html_error'          => php_sapi_name() !== 'cli',
            'error_context_lines' => 7,
            'adapter_class_name'    => isset($options['cache_dir']) && $options['cache_dir']
                ? FileAdapter::class
                : EvalAdapter::class,
            'adapter_options'    => [],
            'shared_variables'    => [],
            'modules'             => [],
            'compiler_class_name' => Compiler::class,
            'compiler_options'    => [
                'filters' => [
                    'cdata' => function ($contents) {
                        return '<![CDATA['.trim($contents).']]>';
                    },
                ],
            ],
        ]);
        $this->setOptionsRecursive($options ?: []);
        $this->forwardOptions();

        $compilerClassName = $this->getOption('compiler_class_name');

        if ($compilerClassName !== Compiler::class && !is_a($compilerClassName, Compiler::class, true)) {
            throw new RendererException(
                "Passed compiler class $compilerClassName is ".
                'not a valid '.Compiler::class
            );
        }

        $this->compiler = new $compilerClassName($this->getOption('compiler_options'));

        $adapterClassName = $this->getOption('adapter_class_name');

        if (!is_a($adapterClassName, AdapterInterface::class, true)) {
            throw new RendererException(
                "Passed adapter class $adapterClassName is ".
                'not a valid '.AdapterInterface::class
            );
        }
        $this->adapter = new $adapterClassName($this, $this->getOption('adapter_options'));

        $this->addModules($this->getOption('modules'));
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

    private function forwardOptions()
    {

        $options = $this->getOptions();

        //Check for top-level options that are forwarded to specific children
        $forwardedOptions = [
            'compiler_options' => [
                'formatter_options' => [],
                'parser_options' => [
                    'lexer_options' => []
                ]
            ]
        ];

        //Compiler
        foreach ([
            'basedir',
            'base_dir',
            'debug',
            'extensions',
            'default_tag',
            'filters',
            'parser_class_name',
            'parser_options',
            'formatter_class_name',
            'formatter_options',
            'mixins_storage_mode',
            'node_compilers',
         ] as $optionName) {
            if (isset($options[$optionName])) {

                $forwardedOptions['compiler_options'][$optionName] = $options[$optionName];
            }
        }

        if (isset($optionName['compiler_modules'])) {

            $forwardedOptions['compiler_options']['modules'] = $optionName['compiler_modules'];
        }

        //Formatter
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

                $forwardedOptions['compiler_options']['formatter_options'][$optionName] = $options[$optionName];
            }
        }

        if (isset($optionName['formatter_modules'])) {

            $forwardedOptions['compiler_options']['formatter_options']['modules'] = $optionName['formatter_modules'];
        }

        if (isset($optionName['formatter_options'])) {

            $forwardedOptions['compiler_options']['formatter_options'] = array_replace_recursive(
                $forwardedOptions['compiler_options']['formatter_options'],
                $optionName['formatter_options']
            );
        }

        //Parser
        if (isset($optionName['parser_modules'])) {

            $forwardedOptions['compiler_options']['parser_options']['modules'] = $optionName['parser_modules'];
        }

        if (isset($optionName['parser_options'])) {

            $forwardedOptions['compiler_options']['parser_options'] = array_replace_recursive(
                $forwardedOptions['compiler_options']['parser_options'],
                $optionName['parser_options']
            );
        }

        //Lexer
        $parserOptions = &$forwardedOptions['compiler_options']['parser_options'];
        if (isset($optionName['lexer_modules'])) {

            $parserOptions['lexer_options']['modules'] = $optionName['lexer_modules'];
        }

        if (isset($optionName['lexer_options'])) {

            $parserOptions['lexer_options'] = array_replace_recursive(
                $parserOptions['lexer_options'],
                $optionName['lexer_options']
            );
        }

        //Adapters
        if (is_a($this->getOption('adapter_class_name'), FileAdapter::class, true)) {
            foreach ([
                'cache_dir',
                'tmp_dir',
                'tmp_dir_function',
                'keep_base_name',
                'modified_check'
            ] as $optionName) {
                if (isset($options[$optionName])) {

                    $forwardedOptions['adapter_options'][$optionName] = $options[$optionName];
                }
            }
        }

        $this->setOptionsRecursive($forwardedOptions);
    }


    private function mergeWithSharedVariables(array $parameters)
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

    private function highlightLine($lineText, $colored, $offset)
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

    private function getErrorMessage($error, $line, $offset, $source, $path, $colored, $parameters = null)
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
            while (count(ob_list_handlers())) {
                ob_end_clean();
            }
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
     * @param int $code
     * @param string $path
     * @param string $source
     * @param array $parameters
     *
     * @throws RendererException
     * @throws Throwable
     */
    public function handleError($error, $code, $path, $source, $parameters = null)
    {
        /* @var Throwable $error */
        $offset = null;
        $exception = $error;
        if ($this->getOption('debug')) {
            $pugError = $error instanceof LocatedException
                ? $error
                : $this->getCompiler()->getFormatter()->getDebugError(
                    $error,
                    $source,
                    $this->getAdapter()->getRenderingFile()
                );
            $line = $pugError->getLocation()->getLine();
            $offset = $pugError->getLocation()->getOffset();
            $sourcePath = $pugError->getLocation()->getPath() ?: $path;
            $source = $sourcePath ? file_get_contents($sourcePath) : $this->lastString;
            $colorSupport = DIRECTORY_SEPARATOR === '\\'
                ? false !== getenv('ANSICON') ||
                'ON' === getenv('ConEmuANSI') ||
                false !== getenv('BABUN_HOME')
                : (false !== getenv('BABUN_HOME')) ||
                function_exists('posix_isatty') &&
                @posix_isatty(STDOUT);
            $isPugError = $error instanceof LocatedException;
            $message = $this->getErrorMessage(
                $error,
                $isPugError ? $error->getLocation()->getLine() : $line,
                $isPugError ? $error->getLocation()->getOffset() : $offset,
                $isPugError ? file_get_contents($error->getLocation()->getPath()) : $source,
                $isPugError ? $error->getLocation()->getPath() : $sourcePath,
                $colorSupport,
                $parameters
            );
            $exception = new RendererException($message, $code, $error);
        }

        $handler = $this->getOption('error_handler');
        if (!$handler) {
            throw $exception;
        }

        $handler($exception);
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
            if ($adapter instanceof CacheInterface) {

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
     * @param $path
     * @return string
     *
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

        return $this->compiler->compile($string, $filename);
    }

    /**
     * @param string $path
     *
     * @return string
     */
    public function compileFile($path)
    {
        $this->lastFile = $path;

        return $this->compiler->compileFile($path);
    }

    /**
     * @param $path
     * @return string
     *
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
     * @param $path
     * @return mixed
     */
    public function display($path)
    {
        $method = file_exists($path) ? 'displayFile' : 'displayString';

        return call_user_func_array([$this, $method], func_get_args());
    }

    /**
     * @param string $path
     * @param array $parameters
     * @return bool|null|string
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
     * @param string $string input string or path
     * @param array $parameters parameters or file name
     * @param string $filename
     * @return bool|null|string
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
