<?php

function secure($string)
{
    if (is_object($string) && method_exists($string, '__toString')) {
        return htmlspecialchars(strval($string));
    }

    return htmlspecialchars(
        is_object($string) || is_array($string)
            ? json_encode($string)
            : strval($string)
    );
}
