<?php

namespace Phug\Renderer\Profiler;

use ArrayObject;
use Phug\Compiler\Event\CompileEvent;
use Phug\Compiler\Event\ElementEvent;
use Phug\Compiler\Event\NodeEvent;
use Phug\Compiler\Event\OutputEvent;
use Phug\CompilerEvent;
use Phug\Event;
use Phug\Formatter;
use Phug\Formatter\Event\DependencyStorageEvent;
use Phug\Formatter\Event\FormatEvent;
use Phug\FormatterEvent;
use Phug\Lexer\Event\LexEvent;
use Phug\Lexer\Event\TokenEvent;
use Phug\LexerEvent;
use Phug\Parser\Event\NodeEvent as ParserNodeEvent;
use Phug\Parser\Event\ParseEvent;
use Phug\ParserEvent;
use Phug\Renderer;
use Phug\Renderer\Event\HtmlEvent;
use Phug\Renderer\Event\RenderEvent;
use Phug\RendererEvent;
use Phug\RendererException;
use Phug\Util\AbstractModule;
use Phug\Util\ModuleContainerInterface;
use SplObjectStorage;

class ProfilerModule extends AbstractModule
{
    /**
     * @var int
     */
    private $startTime = 0;

    /**
     * @var ArrayObject
     */
    private $events = null;

    /**
     * @var array
     */
    private static $profilers = [];

    public function __construct(ArrayObject $events, ModuleContainerInterface $container)
    {
        parent::__construct($container);

        $this->events = $events;
        $this->startTime = microtime(true);
    }

    private function appendParam(Event $event, $key, $value)
    {
        $event->setParams(array_merge($event->getParams(), [
            $key => $value,
        ]));
    }

    private function record(Event $event)
    {
        $time = microtime(true) - $this->startTime;
        $maxTime = $this->getContainer()->getOption('profiler.max_time');
        if ($time * 1000 > $maxTime) {
            throw new RendererException('profiler.max_time of '.$maxTime.'ms exceeded.');
        }
        $this->appendParam($event, '__time', $time);
        $this->events[] = $event;
    }

    private function getDuration($time)
    {
        if (!$time) {
            return '';
        }
        $precision = $this->getContainer()->getOption('profiler.time_precision');
        $unit = 's';
        if ($precision >= 3) {
            $unit = 'ms';
            $time *= 1000;
            $precision -= 3;
        }
        if ($precision >= 3) {
            $unit = 'µs';
            $time *= 1000;
            $precision -= 3;
        }

        return round($time, $precision).$unit;
    }

    private function renderProfile()
    {
        $display = $this->getContainer()->getOption('profiler.display');
        $log = $this->getContainer()->getOption('profiler.log');
        if (!$display && !$log) {
            return '';
        }
        $duration = microtime(true) - $this->startTime;
        $linkedProcesses = new SplObjectStorage();
        array_walk($this->events, function (Event $event, $index) use ($linkedProcesses) {
            $link = $event->getParam('__link');
            if (!method_exists($link, 'getName')) {
                $link = new Event('event_'.$index);
            }
            if (!isset($linkedProcesses[$link])) {
                $linkedProcesses[$link] = [];
            }
            $list = $linkedProcesses[$link];
            $list[] = $event;
            $linkedProcesses[$link] = $list;
        });
        $lineHeight = $this->getContainer()->getOption('profiler.line_height');

        $lines = [];
        $processes = [];
        foreach ($linkedProcesses as $link) {
            /** @var array $list */
            $list = $linkedProcesses[$link];
            $times = array_map(function (Event $event) {
                return $event->getParam('__time');
            }, $list);
            $min = min($times);
            $max = max(max($times), $min + $duration / 20);
            $index = 0;
            foreach ($lines as $level => $line) {
                foreach ($line as $process) {
                    list($from, $to) = $process;
                    if ($to <= $min || $from >= $max) {
                        continue;
                    }
                    $index = $level + 1;
                    continue 2;
                }
                break;
            }
            if (!isset($lines[$index])) {
                $lines[$index] = [];
            }
            $lines[$index][] = [$min, $max];
            $maxSpace = $max;
            $count = count($list);
            for ($i = $count > 1 ? 1 : 0; $i < $count; $i++) {
                /** @var Event $previousEvent */
                $previousEvent = $list[max(0, $i - 1)];
                /** @var Event $currentEvent */
                $currentEvent = $list[$i];
                $min = $previousEvent->getParam('__time');
                $max = $currentEvent->getParam('__time');
                $end = $i === $count - 1 ? $maxSpace : $max;
                $style = [
                    'left'   => ($min * 100 / $duration).'%',
                    'width'  => (($end - $min) * 100 / $duration).'%',
                    'bottom' => (($index + 1) * $lineHeight).'px',
                ];
                if ($currentEvent instanceof FormatEvent) {
                    $style['background'] = '#d8ffd8';
                } elseif ($currentEvent instanceof ParseEvent) {
                    $style['background'] = '#a8ffff';
                } elseif ($previousEvent instanceof ParserNodeEvent) {
                    $style['background'] = '#d8ffff';
                } elseif ($previousEvent instanceof CompileEvent) {
                    $style['background'] = '#ffffa8';
                } elseif ($previousEvent instanceof ElementEvent) {
                    $style['background'] = '#ffffd8';
                } elseif ($previousEvent instanceof TokenEvent) {
                    $style['background'] = '#ffd8d8';
                }
                if ($currentEvent->getName() === 'display') {
                    $style['background'] = '#d8d8ff';
                }
                $processes[] = (object) [
                    'link'     => $link->getName(),
                    'duration' => $this->getDuration($max - $min),
                    'style'    => $style,
                ];
            }
        }

        $render = (new Renderer([
            'debug'   => false,
            'filters' => [
                'no-php' => function ($text) {
                    return str_replace('<?', '<<?= "?" ?>', $text);
                },
            ],
        ]))->renderFile(__DIR__.'/resources/index.pug', [
            'processes' => $processes,
            'duration'  => $this->getDuration($duration),
        ]);

        if ($log) {
            file_put_contents($log, $render);
        }

        return $display ? $render : '';
    }

    public function getId()
    {
        if ($id = array_search($this, static::$profilers)) {
            return $id;
        }

        $id = count(static::$profilers);
        static::$profilers[$id] = $this;

        return $id;
    }

    public function recordDisplayEvent($nodeId)
    {
        /** @var Formatter $formatter */
        $formatter = $this->getContainer();
        if ($formatter->debugIdExists($nodeId)) {
            $node = $formatter->getNodeFromDebugId($nodeId);
            $event = new Event('display');
            $this->appendParam($event, '__link', $node);
            $this->record($event);
        }
    }

    public static function recordProfilerDisplayEvent($profilerId, $nodeId)
    {
        /** @var ProfilerModule $profiler */
        $profiler = static::$profilers[$profilerId];
        $profiler->recordDisplayEvent($nodeId);
    }

    public function attachEvents()
    {
        parent::attachEvents();
        $formatter = $this->getContainer();
        if ($formatter instanceof Formatter) {
            $formatter->setOption('patterns.debug_comment', function ($nodeId) {
                return "\n".static::class.'::recordProfilerDisplayEvent('.
                    var_export($this->getId(), true).', '.
                    var_export($nodeId, true).
                    ");\n// PUG_DEBUG:$nodeId\n";
            });
        }
    }

    public function getEventListeners()
    {
        return [
            RendererEvent::RENDER => function (RenderEvent $event) {
                $this->appendParam($event, '__link', $event);
                $this->record($event);
            },
            RendererEvent::HTML => function (HtmlEvent $event) {
                $this->appendParam($event, '__link', $event->getRenderEvent());
                $this->record($event);

                if ($event->getBuffer()) {
                    $event->setBuffer($this->renderProfile().$event->getBuffer());

                    return;
                }

                $event->setResult($this->renderProfile().$event->getResult());
            },
            CompilerEvent::COMPILE => function (CompileEvent $event) {
                $this->appendParam($event, '__link', $event);
                $this->record($event);
            },
            CompilerEvent::ELEMENT => function (ElementEvent $event) {
                $this->appendParam($event, '__link', $event->getElement()->getOriginNode()->getToken());
                $this->record($event);
            },
            CompilerEvent::NODE => function (NodeEvent $event) {
                $this->appendParam($event, '__link', $event->getNode()->getToken());
                $this->record($event);
            },
            CompilerEvent::OUTPUT => function (OutputEvent $event) {
                $this->appendParam($event, '__link', $event);
                $this->record($event);
            },
            FormatterEvent::DEPENDENCY_STORAGE => function (DependencyStorageEvent $event) {
                $this->appendParam($event, '__link', $event->getDependencyStorage());
                $this->record($event);
            },
            FormatterEvent::FORMAT => function (FormatEvent $event) {
                $this->appendParam($event, '__link', $event->getElement()->getOriginNode()->getToken());
                $this->record($event);
            },
            ParserEvent::PARSE => function (ParseEvent $event) {
                $this->appendParam($event, '__link', $event);
                $this->record($event);
            },
            ParserEvent::DOCUMENT => function (ParserNodeEvent $event) {
                $this->appendParam($event, '__link', $event->getNode()->getToken());
                $this->record($event);
            },
            ParserEvent::STATE_ENTER => function (ParserNodeEvent $event) {
                $this->appendParam($event, '__link', $event->getNode()->getToken());
                $this->record($event);
            },
            ParserEvent::STATE_LEAVE => function (ParserNodeEvent $event) {
                $this->appendParam($event, '__link', $event->getNode()->getToken());
                $this->record($event);
            },
            ParserEvent::STATE_STORE => function (ParserNodeEvent $event) {
                $this->appendParam($event, '__link', $event->getNode()->getToken());
                $this->record($event);
            },
            LexerEvent::LEX => function (LexEvent $event) {
                $this->appendParam($event, '__link', $event);
                $this->record($event);
            },
            LexerEvent::TOKEN => function (TokenEvent $event) {
                $this->appendParam($event, '__link', $event->getToken());
                $this->record($event);
            },
        ];
    }
}
