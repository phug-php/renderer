<?php

namespace Phug\Renderer\Event;

use Phug\Event;
use Phug\RendererEvent;

class HtmlEvent extends Event
{
    private $result;
    private $buffer;
    private $error;

    /**
     * CompileEvent constructor.
     *
     * @param mixed      $result
     * @param string     $buffer
     * @param \Throwable $error
     */
    public function __construct($result, $buffer, $error)
    {
        parent::__construct(RendererEvent::HTML);

        $this->result = $result;
        $this->buffer = $buffer;
        $this->error = $error;
    }

    /**
     * @return string
     */
    public function getBuffer()
    {
        return $this->buffer;
    }

    /**
     * @param string $buffer
     */
    public function setBuffer($buffer)
    {
        $this->buffer = $buffer;
    }

    /**
     * @return \Throwable
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @param \Throwable $error
     */
    public function setError($error)
    {
        $this->error = $error;
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @param mixed $result
     */
    public function setResult($result)
    {
        $this->result = $result;
    }
}
