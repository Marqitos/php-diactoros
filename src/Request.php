<?php
/**
 * This file is part of the Rodas\Diactoros
 *
 * Based on Laminas\Diactoros\Request.php
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

namespace Rodas\Diactoros;

use InvalidArgumentException;
use Override;
use Rodas\Psr\Http\Message\RequestInterface;
use Rodas\Psr\Http\Message\StatusCode;
use Rodas\Psr\Http\Message\StreamInterface;
use Rodas\Psr\Http\Message\UriInterface;

use function strtolower;

/**
 * HTTP Request encapsulation
 *
 * Requests are considered immutable; all methods that might change state are
 * implemented such that they retain the internal state of the current
 * message and return a new instance that contains the changed state.
 */
class Request implements RequestInterface {
    use RequestTrait;

    /**
     * @param null|string|UriInterface $uri URI for the request, if any.
     * @param null|string $method HTTP method for the request, if any.
     * @param string|resource|StreamInterface $body Message body, if any.
     * @param array<non-empty-string, string|string[]> $headers Headers for the message, if any.
     * @throws InvalidArgumentException For any invalid value.
     */
    public function __construct($uri = null, ?string $method = null, $body = 'php://temp', array $headers = []) {
        $this->initialize($uri, $method, $body, $headers);
    }

    /**
     * List of all registered headers, as key => array of values.
     *
     * @var array<non-empty-string, list<string>>
     */
    public protected(set) array $headers = [] {
        get {
            $headers = $this->headers;
            if (! $this->hasHeader('host') &&
                $this->uri->host) {
                $headers['Host'] = [$this->getHostFromUri()];
            }

            return $headers;
        }
        set => $this->headers = $value;
    }
}
