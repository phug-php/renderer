<?php

namespace Phug\Renderer\Profiler;

use ArrayObject;

class EventList extends ArrayObject
{
    /**
     * @var bool
     */
    private $locked = false;

    /**
     * @return bool
     */
    public function isLocked()
    {
        return $this->locked;
    }

    /**
     * @param bool $locked
     */
    public function lock()
    {
        $this->locked = true;
    }
}
