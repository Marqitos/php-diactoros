<?php
/**
 * This file is part of the Rodas\Diactoros
 *
 * Based on Laminas\Diactoros\Exception\InvalidProxyAddressException.php
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

use function get_debug_type;
use function sprintf;

class InvalidProxyAddressException extends RuntimeException {
    public static function forInvalidProxyArgument(mixed $proxy): self {
        $type = get_debug_type($proxy);
        return new self(sprintf(
            'Invalid proxy of type "%s" provided;'
            . ' must be a valid IPv4 or IPv6 address, optionally with a subnet mask provided'
            . ' or an array of such values',
            $type,
        ));
    }

    public static function forAddress(string $address): self {
        return new self(sprintf(
            'Invalid proxy address "%s" provided;'
            . ' must be a valid IPv4 or IPv6 address, optionally with a subnet mask provided',
            $address,
        ));
    }
}
