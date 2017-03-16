<?php

namespace Phug\Renderer;

interface AdapterInterface
{
    /**
     * @param array $options
     */
    public function __construct(array $options);

    /**
     * Return renderer HTML.
     *
     * @param string $php        PHP srouce code
     * @param array  $parameters variables names and values
     *
     * @return string
     */
    public function render($php, array $parameters);

    /**
     * Display renderer HTML.
     *
     * @param string $php        PHP srouce code
     * @param array  $parameters variables names and values
     */
    public function display($php, array $parameters);
}
