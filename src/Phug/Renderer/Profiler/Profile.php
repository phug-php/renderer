<?php

namespace Phug\Renderer\Profiler;

use Phug\Compiler\Event as CompilerEvent;
use Phug\Event;
use Phug\Formatter\Event\FormatEvent;
use Phug\Formatter\Format\HtmlFormat;
use Phug\Lexer\Event as LexerEvent;
use Phug\Lexer\Token;
use Phug\Lexer\TokenInterface;
use Phug\Parser\Event as ParserEvent;
use Phug\Parser\Node\DocumentNode;
use Phug\Renderer;
use Phug\Renderer\Event\HtmlEvent;
use SplObjectStorage;

class Profile
{
    /**
     * @var int
     */
    private $startTime = 0;

    /**
     * @var int
     */
    private $initialMemoryUsage = 0;

    /**
     * @var EventList
     */
    private $events = null;

    /**
     * @var SplObjectStorage
     */
    private $nodesRegister = null;

    /**
     * @var callable
     */
    private $eventDump = null;

    /**
     * @var array
     */
    private $parameters = [];

    public function __construct(
        EventList $events,
        SplObjectStorage $nodesRegister,
        $startTime,
        $initialMemoryUsage,
        $eventDump
    ) {
        $this->events = $events;
        $this->nodesRegister = $nodesRegister;
        $this->startTime = $startTime;
        $this->initialMemoryUsage = $nodesRegister;
        $this->initialMemoryUsage = $initialMemoryUsage;
        $this->eventDump = $eventDump;
    }

    private function getEventLink(Event $event)
    {
        return isset($event->getParams()['__link'])
            ? $event->getParam('__link')
            : null;
    }

    private function getProfilerEvent($name, $object)
    {
        if (!isset($this->nodesRegister[$object])) {
            $this->nodesRegister[$object] = new Event($name);
        }

        return $this->nodesRegister[$object];
    }

    private function getDuration($time, $precision = 3)
    {
        if (!$time) {
            return '0s';
        }
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

    public function compose($timePrecision, $lineHeight)
    {
        $duration = microtime(true) - $this->startTime;
        $linkedProcesses = new SplObjectStorage();
        if ($this->events) {
            foreach ($this->events as $event) {
                /* @var Event $event */
                $link = $this->getEventLink($event);
                if (!($link instanceof TokenInterface) && !method_exists($link, 'getName')) {
                    $link = $link instanceof DocumentNode
                        ? $this->getProfilerEvent('document', $link)
                        : $event;
                }
                if (!isset($linkedProcesses[$link])) {
                    $linkedProcesses[$link] = [];
                }
                $list = $linkedProcesses[$link];
                $list[] = $event;
                $linkedProcesses[$link] = $list;
            }
        }

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
            if ($count === 1 && $list[0] instanceof LexerEvent\TokenEvent) {
                $tokenClass = get_class($list[0]->getToken());
                $tokenSymbol = null;
                $tokenName = null;
                if ($tokenClass === Token\NewLineToken::class) {
                    $tokenSymbol = '↩';
                    $tokenName = 'new line';
                } elseif ($tokenClass === Token\IndentToken::class) {
                    $tokenSymbol = '→';
                    $tokenName = 'indent';
                } elseif ($tokenClass === Token\OutdentToken::class) {
                    $tokenSymbol = '←';
                    $tokenName = 'outdent';
                } elseif ($tokenClass === Token\AttributeStartToken::class) {
                    $tokenSymbol = '(';
                    $tokenName = 'attributes start';
                } elseif ($tokenClass === Token\AttributeEndToken::class) {
                    $tokenSymbol = ')';
                    $tokenName = 'attributes end';
                }
                if ($tokenName) {
                    $processes[] = (object) [
                        'event'    => call_user_func($this->eventDump, $list[0]),
                        'previous' => '#current',
                        'title'    => $tokenName,
                        'link'     => $tokenSymbol,
                        'duration' => '',
                        'style'    => [
                            'font-weight' => 'bold',
                            'font-size'   => '20px',
                            'background'  => '#d7d7d7',
                            'left'        => ($list[0]->getParam('__time') * 100 / $duration).'%',
                            'width'       => '3%',
                            'top'         => (($index + 1) * $lineHeight).'px',
                        ],
                    ];

                    continue;
                }
            }
            for ($i = $count > 1 ? 1 : 0; $i < $count; $i++) {
                /** @var Event $previousEvent */
                $previousEvent = $list[max(0, $i - 1)];
                /** @var Event $currentEvent */
                $currentEvent = $list[$i];
                $min = $previousEvent->getParam('__time');
                $max = $currentEvent->getParam('__time');
                $end = $i === $count - 1 ? $maxSpace : $max;
                $style = [
                    'left'  => ($min * 100 / $duration).'%',
                    'width' => (($end - $min) * 100 / $duration).'%',
                    'top'   => (($index + 1) * $lineHeight).'px',
                ];
                $name = $link instanceof Token\TextToken
                    ? 'text'
                    : (method_exists($link, 'getName')
                        ? $link->getName()
                        : get_class($link)
                    );
                if ($link instanceof Token\MixinCallToken) {
                    $name = '+'.$name;
                }
                if ($link instanceof Token\MixinToken) {
                    $name = 'mixin '.$name;
                }
                if ($currentEvent instanceof LexerEvent\EndLexEvent) {
                    $style['background'] = '#7200c4';
                    $style['color'] = 'white';
                    $name = 'lexing';
                } elseif ($currentEvent instanceof HtmlEvent) {
                    $style['background'] = '#648481';
                    $style['color'] = 'white';
                    $name = 'rendering';
                } elseif ($previousEvent instanceof CompilerEvent\CompileEvent) {
                    $style['background'] = '#ffff78';
                } elseif ($currentEvent instanceof ParserEvent\ParseEvent) {
                    $style['background'] = '#a8ffff';
                } elseif ($previousEvent instanceof FormatEvent) {
                    $name .= ' rendering';
                    $style['background'] = '#d8ffd8';
                } elseif ($previousEvent instanceof CompilerEvent\NodeEvent) {
                    $name .= ' compiling';
                    $style['background'] = '#ffffa8';
                } elseif ($previousEvent instanceof ParserEvent\NodeEvent) {
                    $name .= ' parsing';
                    $style['background'] = '#d8ffff';
                } elseif ($previousEvent instanceof CompilerEvent\ElementEvent) {
                    $name .= ' formatting';
                    $style['background'] = '#d8d8ff';
                } elseif ($previousEvent instanceof LexerEvent\TokenEvent) {
                    $name .= ' lexing';
                    $style['background'] = '#ffd8d8';
                }
                $time = $this->getDuration($max - $min, $timePrecision);
                $processes[] = (object) [
                    'event'    => call_user_func($this->eventDump, $currentEvent),
                    'previous' => $currentEvent === $previousEvent
                        ? '#current'
                        : call_user_func($this->eventDump, $previousEvent),
                    'title'    => $name.': '.$time,
                    'link'     => $name,
                    'duration' => $time,
                    'style'    => $style,
                ];
            }
        }

        $this->parameters = [
            'processes' => $processes,
            'duration'  => $this->getDuration($duration, $timePrecision),
            'height'    => $lineHeight * (count($lines) + 1) + 81,
        ];
    }

    public function render()
    {
        return (new Renderer([
            'debug'           => false,
            'enable_profiler' => false,
            'default_format'  => HtmlFormat::class,
            'filters'         => [
                'no-php' => function ($text) {
                    return str_replace('<?', '<<?= "?" ?>', $text);
                },
            ],
        ]))->renderFile(__DIR__.'/resources/index.pug', $this->parameters);
    }
}
