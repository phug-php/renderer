<?php

namespace Phug\Test;

class CasesTest extends AbstractRendererTest
{
    public function caseProvider()
    {
        return array_map(function ($file) {
            $file = realpath($file);
            $pugFile = substr($file, 0, -5).'.pug';
            $basename = basename($pugFile);

            return [$file, $pugFile, basename($file).' should render '.basename($pugFile)];
        }, glob(__DIR__.'/../cases/*.html'));
    }

    /**
     * @dataProvider caseProvider
     */
    public function testRender($expected, $actual, $message)
    {
        self::assertSameLines(file_get_contents($expected), $this->renderer->render($actual), $message);
    }
}
