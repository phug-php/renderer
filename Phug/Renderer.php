<?php

namespace Phug;

use Phug\Util\Partial\OptionTrait;

class Renderer
{
    use OptionTrait;

    public function __construct(array $options = [])
    {
        $this->setOptionsRecursive([
            'compiler_options' => [
                'formatter_options' => [],
                'parser_options' => [
                    'lexer_options' => [],
                ],
            ],
        ], $options);
    }

    protected function getCompilerOptions()
    {
        $options = $this->getOptions();

        $compilerOptions = $options['compiler_options'];

        if (isset($options['formatter_options'])) {
            $compilerOptions['formatter_options'] = $options['formatter_options'];
        }

        if (isset($options['parser_options'])) {
            $compilerOptions['parser_options'] = $options['parser_options'];
        }

        if (isset($options['lexer_options'])) {
            $compilerOptions['parser_options']['lexer_options'] = $options['lexer_options'];
        }

        return $compilerOptions;
    }

    protected function evaluate($php, array $parameters)
    {
        extract($parameters);
        ob_start();
        eval('?>'.$php);
        $contents = ob_get_contents();
        ob_end_clean();

        return $contents;
    }

    public function render($path, array $parameters = [])
    {
        $compiler = new Compiler($this->getCompilerOptions());

        return $this->evaluate($compiler->compileFile($path), $parameters);
    }

    public function renderString($path, array $parameters = [], $filename = null)
    {
        $compiler = new Compiler($this->getCompilerOptions());

        return $this->evaluate($compiler->compile($path, $filename), $parameters);
    }
}
