<?php

namespace Phug;

use Phug\Util\ModuleInterface;

interface RendererModuleInterface extends ModuleInterface
{
    public function injectRenderer(Renderer $renderer);
}
