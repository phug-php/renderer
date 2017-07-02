<?php

namespace Phug\Renderer\Adapter;

use Phug\Renderer\AbstractAdapter;
use Phug\Renderer\CacheInterface;

class FileAdapter extends AbstractAdapter implements CacheInterface
{
    public function __construct(array $options)
    {
        $this->setOptions([
            'tmp_dir' => sys_get_temp_dir(),
            'tempnam' => 'tempnam',
        ]);

        parent::__construct($options);
    }

    protected function createTemporaryFile()
    {
        return call_user_func(
            $this->getOption('tempnam'),
            $this->getOption('tmp_dir'),
            'pug'
        );
    }

    protected function getCompiledFile($php)
    {
        $file = $this->createTemporaryFile();
        file_put_contents($file, $php);

        return $file;
    }

    public function display($__pug_php, array $__pug_parameters)
    {
        extract($__pug_parameters);
        include $this->getCompiledFile($__pug_php);
    }
}
