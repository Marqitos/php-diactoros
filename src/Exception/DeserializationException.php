<?php
/**
 * This file is part of the Rodas\Diactoros
 *
 * Based on Laminas\Diactoros\Exception\DeserializationException.php
 * laminas/laminas-diactoros (Laminas\Diactoros) from Laminas Project a Series of LF Projects, LLC.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package Rodas\Diactoros
 * @copyright 2026 Marcos Porto <php@marcospor.to>
 * @license https://opensource.org/license/mit The MIT License
 * @link https://marcospor.to/repositories/diactoros
 */

declare(strict_types=1);

namespace Rodas\Diactoros\Exception;

use Throwable;
use UnexpectedValueException;

class DeserializationException extends UnexpectedValueException {
    public static function forInvalidHeader(): self {
        throw new self('Invalid header detected');
    }

    public static function forInvalidHeaderContinuation(): self {
        throw new self('Invalid header continuation');
    }

    public static function forRequestFromArray(Throwable $previous): self {
        return new self('Cannot deserialize request', (int) $previous->getCode(), $previous);
    }

    public static function forResponseFromArray(Throwable $previous): self {
        return new self('Cannot deserialize response', (int) $previous->getCode(), $previous);
    }

    public static function forUnexpectedCarriageReturn(): self {
        throw new self('Unexpected carriage return detected');
    }

    public static function forUnexpectedEndOfHeaders(): self {
        throw new self('Unexpected end of headers');
    }

    public static function forUnexpectedLineFeed(): self {
        throw new self('Unexpected line feed detected');
    }
}
