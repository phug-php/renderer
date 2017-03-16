<?php

namespace Phug\Renderer;

use Phug\Util\Partial\OptionTrait;

abstract class AbstractAdapter implements AdapterInterface
{
    use OptionTrait;

    public function __construct(array $options)
    {
        $this->setOptions($options);
    }

    public function render($php, array $parameters)
    {
        ob_start();
        $this->display($php, $parameters);
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }
}
