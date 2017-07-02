<?php

namespace Phug;

use Exception;
use Phug\Renderer\Adapter\EvalAdapter;
use Phug\Renderer\Adapter\FileAdapter;
use Phug\Util\ModulesContainerInterface;
use Phug\Util\OptionInterface;
use Phug\Util\Partial\ModuleTrait;
use Phug\Util\Partial\OptionTrait;
use Throwable;

class Renderer implements ModulesContainerInterface, OptionInterface
{
    use ModuleTrait;
    use OptionTrait;

    protected $modulesOptionsPaths = [
        CompilerModuleInterface::class  => [],
        FormatterModuleInterface::class => ['formatter_options'],
        ParserModuleInterface::class    => ['parser_options'],
        LexerModuleInterface::class     => ['parser_options', 'lexer_options'],
    ];

    public function __construct(array $options = [])
    {
        $this->setOptionsRecursive([
            'cache'            => null,
            'error_handler'    => null,
            'renderer_adapter' => isset($options['cache'])
                ? FileAdapter::class
                : EvalAdapter::class,
            'renderer_options' => [],
            'shared_variables' => [],
            'modules'          => [],
            'compiler_options' => [
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

    public function share($variables, $value = null)
    {
        if (func_num_args() === 2) {
            $key = $variables;
            $variables = [];
            $variables[$key] = $value;
        }

        $this->setOptionsRecursive([
            'shared_variables' => $variables,
        ]);
    }

    public function getAdapter()
    {
        $adapterClass = $this->getOption('renderer_adapter');

        return new $adapterClass($this->getOption('renderer_options'));
    }

    public function getCompiler()
    {
        return new Compiler($this->getCompilerOptions());
    }

    public function handleError($error, $code, $path, $source)
    {
        /* @var Throwable $error */
        $source = explode("\n", rtrim($source));
        $message = get_class($error);
        if ($path) {
            $message .= ' in '.$path;
        }
        $message .= ":\n".$error->getMessage().' on line '.$error->getLine()."\n\n";
        foreach ($source as $index => $line) {
            $number = strval($index + 1);
            $markLine = $error->getLine() - 1 === $index;
            $line = ($markLine ? '>' : ' ').
                str_repeat(' ', 4 - mb_strlen($number)).$number.' | '.
                $line;
            if (!$markLine) {
                $message .= $line."\n";

                continue;
            }
            $message .= "\033[43;30m".$line."\e[0m\n";
        }

        $exception = new RendererException($message, $code, $error);
        $handler = $this->getOption('error_handler');

        if (!$handler) {
            throw $exception;
        }

        $handler($exception);
    }

    public function callAdapter($method, $path, $source, array $parameters)
    {
        try {
            return $this->getAdapter()->$method(
                $source,
                $this->mergeWithSharedVariables($parameters)
            );
        } catch (Throwable $error) {
            $this->handleError($error, 1, $path, $source);
        } catch (Exception $error) {
            $this->handleError($error, 2, $path, $source);
        }
    }

    public function render($path, array $parameters = [])
    {
        return $this->callAdapter(
            'render',
            $path,
            $this->getCompiler()->compileFile($path),
            $parameters
        );
    }

    public function renderString($path, array $parameters = [], $filename = null)
    {
        return $this->callAdapter(
            'render',
            null,
            $this->getCompiler()->compile($path, $filename),
            $parameters
        );
    }

    public function display($path, array $parameters = [])
    {
        return $this->callAdapter(
            'display',
            $path,
            $this->getCompiler()->compileFile($path),
            $parameters
        );
    }

    public function displayString($path, array $parameters = [], $filename = null)
    {
        return $this->callAdapter(
            'display',
            null,
            $this->getCompiler()->compile($path, $filename),
            $parameters
        );
    }
}
