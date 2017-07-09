<?php

namespace Phug\Renderer;

interface AdapterInterface
{
    /**
     * @param array $options
     */
    public function __construct(array $options);

    /**
     * Return output buffered by the given method.
     *
     * @param callable $display method that output text
     *
     * @return string
     */
    public function captureBuffer(callable $display);

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

    /**
     * An adapter must declare where the rendering is done
     * for debugging stack trace to be accurate.
     *
     * @return string
     */
    public function getRenderingFile();
}
