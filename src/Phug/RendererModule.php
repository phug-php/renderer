<?php

namespace Phug;

use Phug\Util\AbstractModule;
use Phug\Util\ModulesContainerInterface;

class RendererModule extends AbstractModule implements RendererModuleInterface
{
    public function injectRenderer(Renderer $renderer)
    {
        return $renderer;
    }

    public function plug(ModulesContainerInterface $parent)
    {
        parent::plug($this->injectRenderer($parent));
    }
}
