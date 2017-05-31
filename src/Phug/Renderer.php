<?php

namespace Phug;

use Phug\Renderer\Adapter\EvalAdapter;
use Phug\Renderer\Adapter\FileAdapter;
use Phug\Util\Partial\OptionTrait;

class Renderer
{
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
            'compiler_options' => [
                'formatter_options' => [],
                'parser_options'    => [
                    'lexer_options' => [],
                ],
            ],
        ], $options);
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
        return $this->getAdapter()->render(
            $this->getCompiler()->compileFile($path),
            $this->mergeWithSharedVariables($parameters)
        );
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
