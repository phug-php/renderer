<?php

namespace Phug\Test;

use DateTimeImmutable;

class CasesTest extends AbstractRendererTest
{
    public function caseProvider()
    {
        return array_map(function ($file) {
            $file = realpath($file);
            $pugFile = substr($file, 0, -5).'.pug';

            return [$file, $pugFile, basename($pugFile).' should render '.basename($file)];
        }, glob(__DIR__.'/../cases/*.html'));
    }

    /**
     * @dataProvider caseProvider
     */
    public function testRender($expected, $actual, $message)
    {
        self::assertSameLines(file_get_contents($expected), $this->renderer->render($actual), $message);
    }

    /**
     * @group update
     */
    public function testIfCasesAreUpToDate()
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: PHP',
                ],
            ],
        ]);
        $json = json_decode(file_get_contents(
            'https://api.github.com/repos/pugjs/pug/commits?path=packages/pug/test/cases',
            false,
            $context
        ));
        $lastCommit = new DateTimeImmutable($json[0]->commit->author->date);
        $upToDate = new DateTimeImmutable('@'.filemtime(glob(__DIR__.'/../cases/*.pug')[0]));

        self::assertTrue(
            $lastCommit <= $upToDate,
            'Cases should be updated with php tests/update.php, '.
            'then you should commit the new cases.'
        );
    }
}
