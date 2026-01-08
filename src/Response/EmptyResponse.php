<?php
/**
 * This file is part of the Rodas\Diactoros
 *
 * Based on Laminas\Diactoros\Response\EmptyResponse.php
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

namespace Rodas\Diactoros\Response;

use Rodas\Diactoros\Response;
use Rodas\Diactoros\Stream;

/**
 * A class representing empty HTTP responses.
 */
class EmptyResponse extends Response {
    /**
     * Create an empty response with the given status code.
     *
     * @param int $status Status code for the response, if any.
     * @param array<non-empty-string, string|string[]> $headers Headers for the response, if any.
     */
    public function __construct(int $status = 204, array $headers = []) {
        $body = new Stream('php://temp', 'r');
        parent::__construct($body, $status, $headers);
    }

    /**
     * Create an empty response with the given headers.
     *
     * @param array<non-empty-string, string[]> $headers Headers for the response.
     */
    public static function withHeaders(array $headers): EmptyResponse {
        return new static(204, $headers);
    }
}
