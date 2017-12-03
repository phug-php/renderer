<?php

namespace Phug\Renderer\Partial;

use Phug\Renderer\AdapterInterface;
use Phug\Renderer\CacheInterface;
use Phug\Renderer\Event\HtmlEvent;
use Phug\Renderer\Event\RenderEvent;
use Phug\RendererException;
use Phug\Util\SandBox;

/**
 * Trait AdapterTrait: require ModuleContainerInterface to be implemented.
 */
trait AdapterTrait
{

    /**
     * @var AdapterInterface
     */
    private $adapter;

    /**
     * Throw an exception if the given argument (typically an adapter) is not a cache adapter
     * (implement CacheInterface).
     *
     * @param $adapter
     *
     * @throws RendererException
     */
    private function expectCacheAdapter($adapter)
    {
        if (!($adapter instanceof CacheInterface)) {
            throw new RendererException(
                'You cannot use "cache_dir" option with '.get_class($adapter).
                ' because this adapter does not implement '.CacheInterface::class
            );
        }
    }

    /**
     * Get the current adapter used (file, stream, eval or custom adapter provided).
     *
     * @return AdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * Call a method on the adapter (render, renderFile, display, displayFile, more methods can be available depending
     * on the adapter user).
     *
     * @param string   $method
     * @param string   $path
     * @param string   $input
     * @param callable $getSource
     * @param array    $parameters
     *
     * @throws RendererException|\Throwable
     *
     * @return bool|string|null
     */
    public function callAdapter($method, $path, $input, callable $getSource, array $parameters)
    {
        $source = '';

        $renderEvent = new RenderEvent($input, $path, $method, $parameters);
        $this->trigger($renderEvent);
        $input = $renderEvent->getInput();
        $path = $renderEvent->getPath();
        $method = $renderEvent->getMethod();
        $parameters = $renderEvent->getParameters();
        if ($self = $this->getOption('self')) {
            $self = $self === true ? 'self' : strval($self);
            $parameters = [
                $self => $parameters,
            ];
        }

        $sandBox = new SandBox(function () use (&$source, $method, $path, $input, $getSource, $parameters) {
            $adapter = $this->getAdapter();
            $cacheEnabled = (
                $adapter->hasOption('cache_dir') && $adapter->getOption('cache_dir') ||
                $this->hasOption('cache_dir') && $this->getOption('cache_dir')
            );
            if ($cacheEnabled) {
                $this->expectCacheAdapter($adapter);
                $display = function () use ($adapter, $path, $input, $getSource, $parameters) {
                    /* @var CacheInterface $adapter */
                    $adapter->displayCached($path, $input, $getSource, $parameters);
                };

                return in_array($method, ['display', 'displayFile'])
                    ? $display()
                    : $adapter->captureBuffer($display);
            }

            $source = $getSource($path, $input);

            return $adapter->$method(
                $source,
                $this->mergeWithSharedVariables($parameters)
            );
        });

        $htmlEvent = new HtmlEvent(
            $renderEvent,
            $sandBox->getResult(),
            $sandBox->getBuffer(),
            $sandBox->getThrowable()
        );
        $this->trigger($htmlEvent);

        if ($error = $htmlEvent->getError()) {
            $source = $source ?: $getSource($path, $input);
            $this->handleError($error, 1, $path, $source, $parameters, [
                'debug'               => $this->getOption('debug'),
                'error_handler'       => $this->getOption('error_handler'),
                'html_error'          => $this->getOption('html_error'),
                'error_context_lines' => $this->getOption('error_context_lines'),
                'color_support'       => $this->getOption('color_support'),
            ]);
        }

        if ($buffer = $htmlEvent->getBuffer()) {
            echo $buffer;
        }

        return $htmlEvent->getResult();
    }
}
