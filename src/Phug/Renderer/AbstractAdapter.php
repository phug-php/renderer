<?php

namespace Phug\Renderer;

use Exception;
use Phug\Renderer;
use Phug\Util\Partial\OptionTrait;
use Throwable;

abstract class AbstractAdapter implements AdapterInterface
{
    use OptionTrait;

    private $renderer;

    public function __construct(Renderer $renderer, array $options)
    {
        $this->renderer = $renderer;

        $this->setOptions($options);
    }

    public function getRenderer()
    {
        return $this->renderer;
    }

    public function captureBuffer(callable $display)
    {
        $throwable = null;
        ob_start();
        try {
            $display();
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

    public function render($php, array $parameters)
    {
        return $this->captureBuffer(function () use ($php, $parameters) {
            $this->display($php, $parameters);
        });
    }
}
