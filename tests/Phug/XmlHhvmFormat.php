<?php

namespace Phug\Test;

use Phug\Formatter\Format\XmlFormat;

class XmlHhvmFormat extends XmlFormat
{
    const DOCTYPE = '<<?= "?" ?>xml version="1.0" encoding="utf-8" ?>';
}
