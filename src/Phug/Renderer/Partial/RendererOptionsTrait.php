<?php

namespace Phug\Renderer\Partial;

use Phug\Compiler;
use Phug\Renderer\Adapter\EvalAdapter;
use Phug\Renderer\Adapter\FileAdapter;

trait RendererOptionsTrait
{
    protected function fileMatchExtensions($path, $extensions)
    {
        foreach ($extensions as $extension) {
            if (mb_substr($path, -mb_strlen($extension)) === $extension) {
                return true;
            }
        }

        return false;
    }

    protected function getDefaultOptions($options)
    {
        return [
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
        ];
    }
}
