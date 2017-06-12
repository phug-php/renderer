<?php

namespace Phug;

use Phug\Renderer\Adapter\EvalAdapter;
use Phug\Renderer\Adapter\FileAdapter;
use Phug\Util\ModulesContainerInterface;
use Phug\Util\OptionInterface;
use Phug\Util\Partial\ModuleTrait;
use Phug\Util\Partial\OptionTrait;

class Renderer implements ModulesContainerInterface, OptionInterface
{
    use ModuleTrait;
    use OptionTrait;

    public function __construct(array $options = [])
    {
        $this->setOptionsRecursive([
            'cache'            => null,
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

    public function render($path, array $parameters = [])
    {
        $php = $this->getCompiler()->compileFile($path);
        try {
            return $this->getAdapter()->render(
                $php,
                $this->mergeWithSharedVariables($parameters)
            );
        } catch (\Exception $e) {
            echo $e->getMessage()."\n\n".$e->getTraceAsString()."\n\n".$php."\n\n";
            exit;
        }
    }

    public function renderString($path, array $parameters = [], $filename = null)
    {
        return $this->getAdapter()->render(
            $this->getCompiler()->compile($path, $filename),
            $this->mergeWithSharedVariables($parameters)
        );
    }

    public function display($path, array $parameters = [])
    {
        return $this->getAdapter()->display(
            $this->getCompiler()->compileFile($path),
            $this->mergeWithSharedVariables($parameters)
        );
    }

    public function displayString($path, array $parameters = [], $filename = null)
    {
        return $this->getAdapter()->display(
            $this->getCompiler()->compile($path, $filename),
            $this->mergeWithSharedVariables($parameters)
        );
    }
}
