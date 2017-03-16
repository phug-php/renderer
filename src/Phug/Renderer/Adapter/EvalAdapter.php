<?php

namespace Phug\Renderer\Adapter;

use Phug\Renderer\AbstractAdapter;

class EvalAdapter extends AbstractAdapter
{
    public function display($php, array $parameters)
    {
        extract($parameters);
        eval('?>'.$php);
    }
}
