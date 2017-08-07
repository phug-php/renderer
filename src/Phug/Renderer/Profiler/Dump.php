<?php

namespace Phug\Renderer\Profiler;

use Phug\Event;
use Phug\Util\ModuleContainerInterface;
use ReflectionMethod;
use Traversable;

class Dump
{
    private $object;

    public function __construct($object)
    {
        $this->object = $object;
    }

    private function getExposedProperties($object, $deep, $maxDeep = 3)
    {
        $type = gettype($object);
        if (in_array($type, [
            'boolean',
            'integer',
            'double',
            'string',
            'resource',
            'NULL',
        ])) {
            return var_export($object, true);
        }
        $result = '';
        if ($type === 'array' || $object instanceof Traversable) {
            $result .= ($object instanceof Traversable
                    ? get_class($object)
                    : 'array'
                ).' ';
            $count = 0;
            $content = '';
            foreach ($object as $key => $value) {
                if (++$count <= 16) {
                    $content .= "\n" . str_repeat(' ', ($deep + 1) * 2);
                    $content .= $count === 16
                        ? '...'
                        : var_export($key, true) . ' => ' .
                        $this->getExposedProperties($value, $deep + 1);
                }
            }
            $result .= $count
                ? "($count) [$content\n".str_repeat(' ', $deep * 2).']'
                : '[]';

            return $result;
        }

        $result .= get_class($object).' {'.(
            $deep <= $maxDeep
                ? call_user_func(function () use ($object, $deep) {
                $result = "\n";
                foreach (get_class_methods($object) as $method) {
                    if (mb_strlen($result) > 0x80000) {
                        $result .= str_repeat(' ', ($deep + 1) * 2).'...';
                        break;
                    }
                    if (preg_match('/^get[A-Z]/', $method)) {
                        if ($method === 'getOptions') {
                            continue;
                        }
                        $reflexion = new ReflectionMethod($object, $method);
                        if ($reflexion->getNumberOfRequiredParameters() > 0) {
                            continue;
                        }
                        $value = call_user_func([$object, $method]);
                        if ($value instanceof ModuleContainerInterface) {
                            continue;
                        }

                        $result .= str_repeat(' ', ($deep + 1) * 2).
                            mb_substr($method, 3).' => '.
                            ($value instanceof Event
                                ? $value->getName().' event'
                                : $this->getExposedProperties($value, $deep + 1)
                            ).
                            "\n";
                    }
                }

                return $result.str_repeat(' ', $deep * 2);
            })
                : '...'
            ).'}';

        if (mb_strlen($result) > 0x80000) {
            $result = mb_substr($result, 0x80000 - 3).'...';
        }

        return $result;
    }

    /**
     * Return a simplified dump of an object/value.
     *
     * @param mixed $object
     *
     * @return string
     */
    public function dump()
    {
        return $this->getExposedProperties($this->object, 0);
    }
}
