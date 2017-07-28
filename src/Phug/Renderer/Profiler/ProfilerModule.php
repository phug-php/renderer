<?php

namespace Phug\Renderer\Profiler;

use ArrayObject;
use Phug\Compiler\Event\CompileEvent;
use Phug\Compiler\Event\ElementEvent;
use Phug\Compiler\Event\NodeEvent;
use Phug\Compiler\Event\OutputEvent;
use Phug\CompilerEvent;
use Phug\Event;
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
            $count = count($list);
            for ($i = $count > 1 ? 1 : 0; $i < $count; $i++) {
                /** @var Event $previousEvent */
                $previousEvent = $list[max(0, $i - 1)];
                /** @var Event $currentEvent */
                $currentEvent = $list[$i];
                $min = $previousEvent->getParam('__time');
                $originalMax = $currentEvent->getParam('__time');
                $max = max($originalMax, $min + $duration / 20);
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
                $style = [
                    'left'   => ($min * 100 / $duration).'%',
                    'width'  => (($max - $min) * 100 / $duration).'%',
                    'bottom' => ($index * $lineHeight).'px',
                ];
                if ($currentEvent instanceof FormatEvent) {
                    $style['background'] = '#d8ffd8';
                }
                $processes[] = (object) [
                    'link'  => $link->getName(),
                    'style' => $style,
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
            'time_precision' => $this->getContainer()->getOption('profiler.time_precision'),
            'processes'      => $processes,
        ]);

        if ($log) {
            file_put_contents($log, $render);
        }

        return $display ? $render : '';
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
                $this->appendParam($event, '__link', $event->getElement()->getOriginNode());
                $this->record($event);
            },
            CompilerEvent::NODE => function (NodeEvent $event) {
                $this->appendParam($event, '__link', $event->getNode());
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
                $this->appendParam($event, '__link', $event->getElement()->getOriginNode());
                $this->record($event);
            },
            ParserEvent::PARSE => function (ParseEvent $event) {
                $this->appendParam($event, '__link', $event);
                $this->record($event);
            },
            ParserEvent::DOCUMENT => function (ParserNodeEvent $event) {
                $this->appendParam($event, '__link', $event->getNode());
                $this->record($event);
            },
            ParserEvent::STATE_ENTER => function (ParserNodeEvent $event) {
                $this->appendParam($event, '__link', $event->getNode());
                $this->record($event);
            },
            ParserEvent::STATE_LEAVE => function (ParserNodeEvent $event) {
                $this->appendParam($event, '__link', $event->getNode());
                $this->record($event);
            },
            ParserEvent::STATE_STORE => function (ParserNodeEvent $event) {
                $this->appendParam($event, '__link', $event->getNode());
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
