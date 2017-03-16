<?php

namespace Phug\Test;

class CasesTest extends AbstractRendererTest
{
    public function caseProvider()
    {
        return array_map(function ($file) {
            $file = realpath($file);

            return [$file, substr($file, 0, -5).'.pug'];
        }, glob(__DIR__.'/../cases/*.html'));
    }

    /**
     * @dataProvider caseProvider
     */
    public function testRender($expected, $actual)
    {
        self::asssertSameLines(file_get_contents($expected), $this->renderer->render($actual), $actual);
    }
}
