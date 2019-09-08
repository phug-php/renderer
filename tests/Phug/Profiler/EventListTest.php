<?php

namespace Phug\Test\Profiler;

use PHPUnit\Framework\TestCase;
use Phug\Renderer\Profiler\EventList;

/**
 * @coversDefaultClass \Phug\Renderer\Profiler\EventList
 */
class EventListTest extends TestCase
{
    /**
     * @covers ::<public>
     */
    public function testLock()
    {
        $list = new EventList();

        self::assertFalse($list->isLocked());
        self::assertSame($list, $list->lock());
        self::assertTrue($list->isLocked());
        self::assertSame($list, $list->unlock());
        self::assertFalse($list->isLocked());
    }

    /**
     * @covers ::<public>
     */
    public function testReset()
    {
        $list = new EventList();
        $list[] = 5;
        $list[] = 'foo';

        self::assertSame(2, count($list));
        self::assertSame('foo', $list[1]);

        $list->reset();

        self::assertSame(0, count($list));
        self::assertFalse(isset($list[1]));

        $list->lock();

        self::assertTrue($list->isLocked());

        $list->reset();

        self::assertFalse($list->isLocked());
    }
}
