<?php

namespace Phug\Renderer\Partial\Debug;

use Phug\Formatter;
use Phug\Renderer;
use Phug\RendererException;
use Phug\Util\Exception\LocatedException;
use Phug\Util\SandBox;

trait DebuggerTrait
{
    /**
     * @var string
     */
    private $debugString;

    /**
     * @var string
     */
    private $debugFile;

    /**
     * @var Formatter
     */
    private $debugFormatter;

    private function highlightLine($lineText, $colored, $offset, $options)
    {
        if ($options['html_error']) {
            return '<span class="error-line">'.
                (is_null($offset)
                    ? $lineText
                    : mb_substr($lineText, 0, $offset).
                    '<span class="error-offset">'.
                    mb_substr($lineText, $offset, 1).
                    '</span>'.
                    mb_substr($lineText, $offset + 1)
                ).
                "</span>\n";
        }

        if (!$colored) {
            return "$lineText\n";
        }

        return "\033[43;30m".
            (is_null($offset)
                ? $lineText
                : mb_substr($lineText, 0, $offset + 7).
                "\033[43;31m".
                mb_substr($lineText, $offset + 7, 1).
                "\033[43;30m".
                mb_substr($lineText, $offset + 8)
            ).
            "\e[0m\n";
    }

    private function getErrorAsHtml($error, $start, $message, $code, $parameters, $line, $offset, $untilOffset)
    {
        $sandBox = new SandBox(function () use (
            $error,
            $start,
            $message,
            $code,
            $parameters,
            $line,
            $offset,
            $untilOffset
        ) {
            /* @var \Throwable $error */
            $trace = '## '.$error->getFile().'('.$error->getLine().")\n".$error->getTraceAsString();
            (new Renderer([
                'debug'   => false,
                'filters' => [
                    'no-php' => function ($text) {
                        return str_replace('<?', '<<?= "?" ?>', $text);
                    },
                ],
            ]))->displayFile(__DIR__.'/resources/index.pug', [
                'title'       => $error->getMessage(),
                'trace'       => $trace,
                'start'       => $start,
                'untilOffset' => htmlspecialchars($untilOffset),
                'line'        => $line,
                'offset'      => $offset,
                'message'     => trim($message),
                'code'        => $code,
                'parameters'  => $parameters ? print_r($parameters, true) : '',
            ]);
        });

        if ($throwable = $sandBox->getThrowable()) {
            return '<pre>'.$throwable->getMessage()."\n\n".$throwable->getTraceAsString().'</pre>';
        }

        return $sandBox->getBuffer();
    }

    private function getErrorMessage($error, $line, $offset, $source, $path, $colored, $parameters, $options)
    {
        /* @var \Throwable $error */
        $source = explode("\n", rtrim($source));
        $errorType = get_class($error);
        $message = $errorType;
        if ($path) {
            $message .= ' in '.$path;
        }
        $message .= ":\n".$error->getMessage().' on line '.$line.
            (is_null($offset) ? '' : ', offset '.$offset)."\n\n";
        $contextLines = $options['error_context_lines'];
        $code = '';
        $untilOffset = mb_substr($source[$line - 1], 0, $offset ?: 0) ?: '';
        $htmlError = $options['html_error'];
        $start = null;
        foreach ($source as $index => $lineText) {
            if (abs($index + 1 - $line) > $contextLines) {
                continue;
            }
            if (is_null($start)) {
                $start = $index + 1;
            }
            $number = strval($index + 1);
            $markLine = $line - 1 === $index;
            if (!$htmlError) {
                $lineText = ($markLine ? '>' : ' ').
                    str_repeat(' ', 4 - mb_strlen($number)).$number.' | '.
                    $lineText;
            }
            if (!$markLine) {
                $code .= $lineText."\n";

                continue;
            }
            $code .= $this->highlightLine($lineText, $colored, $offset, $options);
            if (!$htmlError && !is_null($offset)) {
                $code .= str_repeat('-', $offset + 7)."^\n";
            }
        }
        if ($htmlError) {
            return $this->getErrorAsHtml($error, $start, $message, $code, $parameters, $line, $offset, $untilOffset);
        }

        return $message.$code;
    }

    private function getRendererException($error, $code, $line, $offset, $source, $sourcePath, $parameters, $options)
    {
        $colorSupport = $options['color_support'];
        if (is_null($colorSupport)) {
            $colorSupport = $this->hasColorSupport();
        }
        $isPugError = $error instanceof LocatedException;
        /* @var LocatedException $error */

        return new RendererException($this->getErrorMessage(
            $error,
            $isPugError ? $error->getLocation()->getLine() : $line,
            $isPugError ? $error->getLocation()->getOffset() : $offset,
            $isPugError && ($path = $error->getLocation()->getPath())
                ? file_get_contents($path)
                : $source,
            $isPugError ? $error->getLocation()->getPath() : $sourcePath,
            $colorSupport,
            $parameters,
            $options
        ), $code, $error);
    }

    private function hasColorSupport()
    {
        // @codeCoverageIgnoreStart
        return DIRECTORY_SEPARATOR === '\\'
            ? false !== getenv('ANSICON') ||
            'ON' === getenv('ConEmuANSI') ||
            false !== getenv('BABUN_HOME')
            : (false !== getenv('BABUN_HOME')) ||
            function_exists('posix_isatty') &&
            @posix_isatty(STDOUT);
        // @codeCoverageIgnoreEnd
    }

    private function getDebuggedException($error, $code, $source, $path, $parameters, $options)
    {
        /* @var \Throwable $error */
        $isLocatedError = $error instanceof LocatedException;

        if ($isLocatedError && is_null($error->getLine())) {
            return $error;
        }

        $pugError = $isLocatedError
            ? $error
            : $this->getDebugFormatter()->getDebugError(
                $error,
                $source,
                $path
            );

        if (!($pugError instanceof LocatedException)) {
            return $pugError;
        }

        $line = $pugError->getLocation()->getLine();
        $offset = $pugError->getLocation()->getOffset();
        $sourcePath = $pugError->getLocation()->getPath() ?: $path;

        if ($sourcePath && !file_exists($sourcePath)) {
            return $error;
        }

        $source = $sourcePath ? file_get_contents($sourcePath) : $this->debugString;

        return $this->getRendererException($error, $code, $line, $offset, $source, $sourcePath, $parameters, $options);
    }

    /**
     * @param string $debugFile
     */
    protected function setDebugFile($debugFile)
    {
        $this->debugFile = $debugFile;
    }

    /**
     * @param string $debugString
     */
    protected function setDebugString($debugString)
    {
        $this->debugString = $debugString;
    }

    /**
     * @param Formatter $debugFormatter
     */
    protected function setDebugFormatter(Formatter $debugFormatter)
    {
        $this->debugFormatter = $debugFormatter;
    }

    /**
     * @return Formatter
     */
    public function getDebugFormatter()
    {
        return $this->debugFormatter ?: new Formatter();
    }

    /**
     * Handle error occurred in compiled PHP.
     *
     * @param \Throwable $error
     * @param int        $code
     * @param string     $path
     * @param string     $source
     * @param array      $parameters
     * @param array      $options
     *
     * @throws RendererException
     * @throws \Throwable
     */
    public function handleError($error, $code, $path, $source, $parameters, $options)
    {
        /* @var \Throwable $error */
        $exception = $options['debug']
            ? $this->getDebuggedException($error, $code, $source, $path, $parameters, $options)
            : $error;

        $handler = $options['error_handler'];
        if (!$handler) {
            // @codeCoverageIgnoreStart
            if ($options['debug'] && $options['html_error']) {
                echo $exception->getMessage();
                exit(1);
            }
            // @codeCoverageIgnoreEnd
            throw $exception;
        }

        $handler($exception);
    }
}
