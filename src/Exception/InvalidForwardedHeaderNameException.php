<?php
/**
 * This file is part of the Rodas\Diactoros
 *
 * Based on Laminas\Diactoros\Exception\InvalidForwardedHeaderNameException.php
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

use Rodas\Diactoros\ServerRequestFilter\FilterUsingXForwardedHeaders;

use RuntimeException;

use function get_debug_type;
use function is_string;
use function sprintf;

class InvalidForwardedHeaderNameException extends RuntimeException {
    public static function forHeader(mixed $name): self {
        if (! is_string($name)) {
            $name = sprintf('(value of type %s)', get_debug_type($name));
        }

        return new self(sprintf(
            'Invalid X-Forwarded-* header name "%s" provided to %s',
            $name,
            FilterUsingXForwardedHeaders::class
        ));
    }
}
