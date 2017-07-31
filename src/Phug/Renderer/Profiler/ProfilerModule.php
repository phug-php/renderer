<?php

namespace Phug\Renderer\Profiler;

use Phug\Compiler\Event\CompileEvent;
use Phug\Compiler\Event\ElementEvent;
use Phug\Compiler\Event\NodeEvent;
use Phug\Compiler\Event\OutputEvent;
use Phug\CompilerEvent;
use Phug\Event;
use Phug\Formatter;
use Phug\Formatter\Event\DependencyStorageEvent;
use Phug\Formatter\Event\FormatEvent;
use Phug\Formatter\Format\HtmlFormat;
use Phug\FormatterEvent;
use Phug\Lexer\Event\EndLexEvent;
use Phug\Lexer\Event\LexEvent;
use Phug\Lexer\Event\TokenEvent;
use Phug\Lexer\Token\AttributeEndToken;
use Phug\Lexer\Token\AttributeStartToken;
use Phug\Lexer\Token\IndentToken;
use Phug\Lexer\Token\MixinCallToken;
use Phug\Lexer\Token\MixinToken;
use Phug\Lexer\Token\NewLineToken;
use Phug\Lexer\Token\OutdentToken;
use Phug\Lexer\Token\TextToken;
use Phug\Lexer\TokenInterface;
use Phug\LexerEvent;
use Phug\Parser\Event\NodeEvent as ParserNodeEvent;
use Phug\Parser\Event\ParseEvent;
use Phug\Parser\Node\DocumentNode;
use Phug\Parser\NodeInterface;
use Phug\ParserEvent;
use Phug\Renderer;
use Phug\Renderer\Event\HtmlEvent;
use Phug\Renderer\Event\RenderEvent;
use Phug\RendererEvent;
use Phug\Util\AbstractModule;
use Phug\Util\ModuleContainerInterface;
use Phug\Util\SandBox;
use SplObjectStorage;

class ProfilerModule extends AbstractModule
{
    /**
     * @var int
     */
    private $startTime = 0;

    /**
     * @var EventList
     */
    private $events = null;

    /**
     * @var SplObjectStorage
     */
    private $nodesRegister = null;

    /**
     * @var array
     */
    private static $profilers = [];

    public function __construct(EventList $events, ModuleContainerInterface $container)
    {
        parent::__construct($container);

        $this->events = $events;
        $this->startTime = microtime(true);
        $this->nodesRegister = new SplObjectStorage();
    }

    public function kill()
    {
        $this->events->lock();
    }

    public function isAlive()
    {
        return !$this->events->isLocked();
    }

    private function appendParam(Event $event, $key, $value)
    {
        $event->setParams(array_merge($event->getParams(), [
            $key => $value,
        ]));
    }

    private function appendNode(Event $event, $node)
    {
        if ($node instanceof NodeInterface) {
            $this->appendParam($event, '__location', $node->getSourceLocation());
            $this->appendParam($event, '__link', $node->getToken() ?: $node);
        }
    }

    private function throwException(Event $event, $message)
    {
        $params = $event->getParams();
        $this->kill();

        throw isset($params['__location'])
            ? new ProfilerLocatedException($params['__location'], $message)
            : new ProfilerException($message);
    }

    private function record(Event $event)
    {
        $time = microtime(true) - $this->startTime;
        $container = $this->getContainer();
        $maxTime = $container->getOption('execution_max_time');
        if ($maxTime > -1 && $time * 1000 > $maxTime) {
            $this->throwException($event, 'profiler.max_time of '.$maxTime.'ms exceeded.');
        }
        $this->appendParam($event, '__time', $time);
        if ($container->getOption('profiler.display') || $container->getOption('profiler.log')) {
            $this->events[] = $event;
        }
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

    private function getDump($value)
    {
        $sandBox = new SandBox(function () use ($value) {
            var_dump($value);
        });

        return $sandBox->getBuffer();
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

    private function renderProfile()
    {
        $display = $this->getContainer()->getOption('profiler.display');
        $log = $this->getContainer()->getOption('profiler.log');
        if (!$display && !$log) {
            return '';
        }
        $duration = microtime(true) - $this->startTime;
        $linkedProcesses = new SplObjectStorage();
        array_walk($this->events, function (Event $event) use ($linkedProcesses) {
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
            if ($count === 1 && $list[0] instanceof TokenEvent) {
                $tokenClass = get_class($list[0]->getToken());
                $tokenSymbol = null;
                $tokenName = null;
                if ($tokenClass === NewLineToken::class) {
                    $tokenSymbol = '↩';
                    $tokenName = 'new line';
                } elseif ($tokenClass === IndentToken::class) {
                    $tokenSymbol = '→';
                    $tokenName = 'indent';
                } elseif ($tokenClass === OutdentToken::class) {
                    $tokenSymbol = '←';
                    $tokenName = 'outdent';
                } elseif ($tokenClass === AttributeStartToken::class) {
                    $tokenSymbol = '(';
                    $tokenName = 'attributes start';
                } elseif ($tokenClass === AttributeEndToken::class) {
                    $tokenSymbol = ')';
                    $tokenName = 'attributes end';
                }
                if ($tokenName) {
                    $processes[] = (object) [
                        'event'    => $this->getDump($list[0]),
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
                $name = $link instanceof TextToken
                    ? 'text'
                    : (method_exists($link, 'getName')
                        ? $link->getName()
                        : get_class($link)
                    );
                if ($link instanceof MixinCallToken) {
                    $name = '+'.$name;
                }
                if ($link instanceof MixinToken) {
                    $name = 'mixin '.$name;
                }
                if ($currentEvent instanceof EndLexEvent) {
                    $style['background'] = '#7200c4';
                    $style['color'] = 'white';
                    $name = 'lexing';
                } elseif ($currentEvent instanceof HtmlEvent) {
                    $style['background'] = '#648481';
                    $style['color'] = 'white';
                    $name = 'rendering';
                } elseif ($previousEvent instanceof CompileEvent) {
                    $style['background'] = '#ffff78';
                } elseif ($currentEvent instanceof ParseEvent) {
                    $style['background'] = '#a8ffff';
                } elseif ($previousEvent instanceof FormatEvent) {
                    $name .= ' rendering';
                    $style['background'] = '#d8ffd8';
                } elseif ($previousEvent instanceof NodeEvent) {
                    $name .= ' compiling';
                    $style['background'] = '#ffffa8';
                } elseif ($previousEvent instanceof ParserNodeEvent) {
                    $name .= ' parsing';
                    $style['background'] = '#d8ffff';
                } elseif ($previousEvent instanceof ElementEvent) {
                    $name .= ' formatting';
                    $style['background'] = '#d8d8ff';
                } elseif ($previousEvent instanceof TokenEvent) {
                    $name .= ' lexing';
                    $style['background'] = '#ffd8d8';
                }
                $time = $this->getDuration($max - $min);
                $processes[] = (object) [
                    'event'    => $this->getDump($currentEvent),
                    'previous' => $currentEvent === $previousEvent
                        ? '#current'
                        : $this->getDump($previousEvent),
                    'title'    => $name.': '.$time,
                    'link'     => $name,
                    'duration' => $time,
                    'style'    => $style,
                ];
            }
        }

        $render = (new Renderer([
            'debug'           => false,
            'enable_profiler' => false,
            'default_format'  => HtmlFormat::class,
            'filters'         => [
                'no-php' => function ($text) {
                    return str_replace('<?', '<<?= "?" ?>', $text);
                },
            ],
        ]))->renderFile(__DIR__.'/resources/index.pug', [
            'processes' => $processes,
            'duration'  => $this->getDuration($duration),
            'height'    => $lineHeight * (count($lines) + 1) + 81,
        ]);

        if ($log) {
            file_put_contents($log, $render);
        }

        return $display ? $render : '';
    }

    public function getDebugId($nodeId)
    {
        $id = count(static::$profilers);
        static::$profilers[$id] = [$this, $nodeId];

        return $id;
    }

    public function recordDisplayEvent($nodeId)
    {
        if (!$this->isAlive()) {
            return;
        }
        /** @var Formatter $formatter */
        $formatter = $this->getContainer();
        if ($formatter->debugIdExists($nodeId)) {
            $event = new Event('display');
            $this->appendNode($event, $formatter->getNodeFromDebugId($nodeId));
            $this->record($event);
        }
    }

    public static function recordProfilerDisplayEvent($debugId)
    {
        /** @var ProfilerModule $profiler */
        list($profiler, $nodeId) = static::$profilers[$debugId];
        $profiler->recordDisplayEvent($nodeId);
    }

    public function attachEvents()
    {
        parent::attachEvents();
        $formatter = $this->getContainer();
        if ($formatter instanceof Formatter) {
            $formatter->setOption('patterns.debug_comment', function ($nodeId) {
                return "\n".static::class.'::recordProfilerDisplayEvent('.
                    var_export($this->getDebugId($nodeId), true).
                    ");\n// PUG_DEBUG:$nodeId\n";
            });
        }
    }

    public function getEventListeners()
    {
        $eventListeners = array_map(function (callable $eventListener) {
            return function (Event $event) use ($eventListener) {
                if ($this->isAlive() && $eventListener($event) !== false) {
                    $this->record($event);
                }
            };
        }, [
            RendererEvent::RENDER => function (RenderEvent $event) {
                $this->appendParam($event, '__link', $event);
            },
            CompilerEvent::COMPILE => function (CompileEvent $event) {
                $this->appendParam($event, '__link', $event);
            },
            CompilerEvent::ELEMENT => function (ElementEvent $event) {
                $this->appendNode($event, $event->getElement()->getOriginNode());
            },
            CompilerEvent::NODE => function (NodeEvent $event) {
                $this->appendNode($event, $event->getNode());
            },
            CompilerEvent::OUTPUT => function (OutputEvent $event) {
                $this->appendParam($event, '__link', $event->getCompileEvent());
            },
            FormatterEvent::DEPENDENCY_STORAGE => function (DependencyStorageEvent $event) {
                $this->appendParam($event, '__link', $event->getDependencyStorage());
            },
            FormatterEvent::FORMAT => function (FormatEvent $event) {
                $this->appendNode($event, $event->getElement()->getOriginNode());
            },
            ParserEvent::PARSE => function (ParseEvent $event) {
                $this->appendParam($event, '__link', $event);
            },
            ParserEvent::DOCUMENT => function (ParserNodeEvent $event) {
                $this->appendNode($event, $event->getNode());
            },
            ParserEvent::STATE_ENTER => function (ParserNodeEvent $event) {
                $this->appendNode($event, $event->getNode());
            },
            ParserEvent::STATE_LEAVE => function (ParserNodeEvent $event) {
                $this->appendNode($event, $event->getNode());
            },
            ParserEvent::STATE_STORE => function (ParserNodeEvent $event) {
                $this->appendNode($event, $event->getNode());
            },
            LexerEvent::LEX => function (LexEvent $event) {
                $this->appendParam($event, '__link', $event);
            },
            LexerEvent::END_LEX => function (EndLexEvent $event) {
                $this->appendParam($event, '__link', $event->getLexEvent());
            },
            LexerEvent::TOKEN => function (TokenEvent $event) {
                $token = $event->getToken();
                $this->appendParam($event, '__location', $token->getSourceLocation());
                $this->appendParam($event, '__link', $token);
            },
        ]);

        $eventListeners[RendererEvent::HTML] = function (HtmlEvent $event) {
            $this->appendParam($event, '__link', $event->getRenderEvent());
            if ($this->isAlive()) {
                $this->record($event);
            }

            if ($event->getBuffer()) {
                $event->setBuffer($this->renderProfile().$event->getBuffer());
            }

            $event->setResult($this->renderProfile().$event->getResult());
        };

        return $eventListeners;
    }
}
