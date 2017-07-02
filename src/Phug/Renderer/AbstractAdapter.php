<?php

namespace Phug\Renderer;

use Exception;
use Phug\Util\Partial\OptionTrait;
use Throwable;

abstract class AbstractAdapter implements AdapterInterface
{
    use OptionTrait;

    public function __construct(array $options)
    {
        $this->setOptions($options);
    }

    public function render($php, array $parameters)
    {
        $throwable = null;
        ob_start();
        try {
            $this->display($php, $parameters);
        } catch (Throwable $e) { // PHP 7
            $throwable = $e;
        } catch (Exception $e) { // PHP 5
            $throwable = $e;
        }
        $html = ob_get_contents();
        ob_end_clean();

        if ($throwable) {
            throw $throwable;
        }

        return $html;
    }
}
