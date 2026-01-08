<?php
/**
 * This file is part of the Rodas\Diactoros
 *
 * Based on Laminas\Diactoros\Exception\UnwritableStreamException.php
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

use RuntimeException;

class UnwritableStreamException extends RuntimeException {
    public static function dueToConfiguration(): self {
        return new self('Stream is not writable');
    }

    public static function dueToMissingResource(): self {
        return new self('No resource available; cannot write');
    }

    public static function dueToPhpError(): self {
        return new self('Error writing to stream');
    }

    public static function forCallbackStream(): self {
        return new self('Callback streams cannot write');
    }
}
