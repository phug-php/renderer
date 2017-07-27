<?php

namespace Phug\Renderer\Profiler;

use Phug\AbstractRendererModule;
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
use Phug\Parser\Event\ParseEvent;
use Phug\ParserEvent;
use Phug\Renderer\Event\HtmlEvent;
use Phug\Renderer\Event\RenderEvent;
use Phug\RendererEvent;
use Phug\Util\ModuleContainerInterface;

class ProfilerModule extends AbstractRendererModule
{
    private $events = [];

    public function __construct(ModuleContainerInterface $container)
    {
        parent::__construct($container);

        $this->record(new Event('profiler', $this));
    }

    private function record(Event $event)
    {
        $event->setParams(array_merge($event->getParams(), [
            '__time' => microtime(true),
        ]));
        $this->events[] = $event;
    }

    private function renderProfile()
    {
        $start = $this->events[0]->getParam('__time');

        return implode("\n", array_map(function (Event $event) use ($start) {
            return str_pad($event->getParam('__time') - $start, 30, ' ').$event->getName();
        }, $this->events));
    }

    public function getEventListeners()
    {
        return [
            RendererEvent::RENDER => function (RenderEvent $event) {
                $this->record($event);
            },
            RendererEvent::HTML => function (HtmlEvent $event) {
                $this->record($event);

                if ($event->getBuffer()) {
                    $event->setBuffer($this->renderProfile());

                    return;
                }

                $event->setResult($this->renderProfile());
            },
            CompilerEvent::COMPILE => function (CompileEvent $event) {
                $this->record($event);
            },
            CompilerEvent::ELEMENT => function (ElementEvent $event) {
                $this->record($event);
            },
            CompilerEvent::NODE => function (NodeEvent $event) {
                $this->record($event);
            },
            CompilerEvent::OUTPUT => function (OutputEvent $event) {
                $this->record($event);
            },
            FormatterEvent::DEPENDENCY_STORAGE => function (DependencyStorageEvent $event) {
                $this->record($event);
            },
            FormatterEvent::FORMAT => function (FormatEvent $event) {
                $this->record($event);
            },
            ParserEvent::PARSE => function (ParseEvent $event) {
                $this->record($event);
            },
            ParserEvent::DOCUMENT => function (NodeEvent $event) {
                $this->record($event);
            },
            ParserEvent::STATE_ENTER => function (NodeEvent $event) {
                $this->record($event);
            },
            ParserEvent::STATE_LEAVE => function (NodeEvent $event) {
                $this->record($event);
            },
            ParserEvent::STATE_STORE => function (NodeEvent $event) {
                $this->record($event);
            },
            LexerEvent::LEX => function (LexEvent $event) {
                $this->record($event);
            },
            LexerEvent::TOKEN => function (TokenEvent $event) {
                $this->record($event);
            },
        ];
    }
}
