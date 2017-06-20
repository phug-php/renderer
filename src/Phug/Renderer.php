<?php

namespace Phug;

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
            ],
        ], $options);

        $modules = $this->getOption('modules');
        $optionsPaths = [
            CompilerModuleInterface::class  => ['compiler_options'],
            FormatterModuleInterface::class => ['compiler_options', 'formatter_options'],
            ParserModuleInterface::class    => ['compiler_options', 'parser_options'],
            LexerModuleInterface::class     => ['compiler_options', 'parser_options', 'lexer_options'],
        ];

        foreach ($modules as &$module) {
            foreach ($optionsPaths as $interface => $optionPath) {
                if (is_subclass_of($module, $interface)) {
                    $list = $this->getOption($optionPath);
                    $list = is_array($list) && isset($list['modules']) && is_array($list['modules'])
                        ? $list['modules']
                        : [];
                    $list[] = $module;
                    $optionPath[] = 'modules';
                    $this->setOption($optionPath, $list);
                    $module = false;

                    break;
                }
            }
        }

        $this->setExpectedModuleType(RendererModuleInterface::class);
        $this->addModules(array_filter($modules));
    }

    protected function getCompilerOptions()
    {
        $options = $this->getOptions();

        $compilerOptions = $options['compiler_options'];

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
                $compilerOptions['formatter_options'][$optionName] = $options[$optionName];
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
                $compilerOptions[$optionName] = $options[$optionName];
            }
        }

        if (isset($options['lexer_options'])) {
            $compilerOptions['parser_options']['lexer_options'] = $options['lexer_options'];
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

    public function handleError(Throwable $error, $code, $path, $source)
    {
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
                str_repeat(' ', 4 - strlen($number)).$number.' | '.
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
