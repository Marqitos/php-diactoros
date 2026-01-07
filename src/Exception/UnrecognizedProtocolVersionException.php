<?php

declare(strict_types=1);

namespace Rodas\Diactoros\Exception;

use UnexpectedValueException;

use function sprintf;

class UnrecognizedProtocolVersionException extends UnexpectedValueException {
    public static function forVersion(string $version): self {
        return new self(sprintf('Unrecognized protocol version (%s)', $version));
    }
}
