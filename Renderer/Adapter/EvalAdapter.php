<?php

namespace Phug\Renderer\Adapter;

use Exception;
use Phug\Formatter\Util\PhpUnwrapString;
use Phug\Renderer\AbstractAdapter;
use Phug\RendererException;
use Throwable;

/**
 * Renderer using `eval()` PHP language construct.
 *
 * Note: this is not more risky than other evaluation ways. Every adapter will execute the code of the Pug
 * templates (inside code statements and expressions). To be safe, you should not render uncontrolled templates
 * unless your environment is a restricted sandbox.
 */
class EvalAdapter extends AbstractAdapter
{
    public function display($__pug_php, array $__pug_parameters)
    {
        if ($this->getOption('debug')) {
            $error = null;

            try {
                $__pug_php_unwrapped_code = PhpUnwrapString::withoutOpenTag($__pug_php);
                file_put_contents('temp.php', "<?php \n$__pug_php_unwrapped_code");
                $this->execute(function () use ($__pug_php, $__pug_php_unwrapped_code, &$__pug_parameters) {
                    extract($__pug_parameters);
                    eval($__pug_php_unwrapped_code);
                }, $__pug_parameters);
            } catch (Throwable $exception) {
                $error = $exception;
            } catch (Exception $exception) {
                $error = $exception;
            }

            if ($error !== null) {
                $code = '';
                $lineNumber = $error->getLine();
                $length = max(2, strlen("$lineNumber")) + 3;

                foreach (explode("\n", $__pug_php_unwrapped_code) as $index => $line) {
                    $number = $index + 1;

                    if (abs($number - $lineNumber) > 150) {
                        continue;
                    }

                    $code .= "\n".($number === $lineNumber ? '> ' : '  ').str_pad($number, $length, ' ', STR_PAD_LEFT) . ' | ' . $line;
                }

                throw new RendererException(
                    $error->getMessage()."\nLine: ".$lineNumber.
                    "\nEvaluating:$code"
                );
            }

            return;
        }

        $this->execute(function () use ($__pug_php, &$__pug_parameters) {
            extract($__pug_parameters);
            eval(PhpUnwrapString::withoutOpenTag($__pug_php));
        }, $__pug_parameters);
    }
}
