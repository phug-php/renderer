<?php

namespace Phug\Renderer\Profiler;

use Phug\Compiler\Event\CompileEvent;
use Phug\Compiler\Event\ElementEvent;
use Phug\Compiler\Event\NodeEvent as CompilerNodeEvent;
use Phug\Formatter\Event\FormatEvent;
use Phug\Lexer\Event\EndLexEvent;
use Phug\Lexer\Event\TokenEvent;
use Phug\Lexer\Token\MixinCallToken;
use Phug\Lexer\Token\MixinToken;
use Phug\Lexer\Token\TextToken;
use Phug\Parser\Event\NodeEvent as ParserNodeEvent;
use Phug\Parser\Event\ParseEvent;
use Phug\Renderer\Event\HtmlEvent;

class LinkDump
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $style;

    public function __construct($link, $currentEvent, $previousEvent)
    {
        $style = [];
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
        } elseif ($previousEvent instanceof CompilerNodeEvent) {
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

        $this->name = $name;
        $this->style = $style;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getStyle()
    {
        return $this->style;
    }
}
