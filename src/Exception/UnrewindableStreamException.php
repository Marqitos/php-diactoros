<?php

declare(strict_types=1);

namespace Rodas\Diactoros\Exception;

use RuntimeException;

class UnrewindableStreamException extends RuntimeException {
    public static function forCallbackStream(): self {
        return new self('Callback streams cannot rewind position');
    }
}
