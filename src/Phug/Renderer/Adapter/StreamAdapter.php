<?php

namespace Phug\Renderer\Adapter;

use Phug\Renderer\AbstractAdapter;

class StreamAdapter extends AbstractAdapter
{
    public function __construct(array $options)
    {
        $this->setOptions([
            'stream_name'   => 'pug',
            'stream_suffix' => '.stream',
        ]);

        parent::__construct($options);
    }

    public function display($php, array $parameters)
    {
        extract($parameters);
        include $this->getOption('stream_name').
            $this->getOption('stream_suffix').
            '://data;'.
            $php;
    }
}
