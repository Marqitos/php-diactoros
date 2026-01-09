<?php
/**
 * This file is part of the Rodas\Diactoros
 *
 * Based on Laminas\Diactoros\Response\HtmlResponse.php
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

use InvalidArgumentException;
use Rodas\Diactoros\Exception;
use Rodas\Diactoros\Response;
use Rodas\Diactoros\Stream;
use Rodas\Psr\Http\Message\StreamInterface;

use function get_debug_type;
use function is_string;
use function sprintf;

/**
 * HTML response.
 *
 * Allows creating a response by passing an HTML string to the constructor;
 * by default, sets a status code of 200 and sets the Content-Type header to
 * text/html.
 */
class HtmlResponse extends Response {
    use InjectContentTypeTrait;

    /**
     * Create an HTML response.
     *
     * Produces an HTML response with a Content-Type of text/html and a default
     * status of 200.
     *
     * @param string|StreamInterface $html HTML or stream for the message body.
     * @param int $status Integer status code for the response; 200 by default.
     * @param array<non-empty-string, string|string[]> $headers Array of headers to use at initialization.
     * @throws InvalidArgumentException If $html is neither a string or stream.
     */
    public function __construct($html, int $status = 200, array $headers = []) {
        parent::__construct(
            $this->createBody($html),
            $status,
            $this->injectContentType('text/html; charset=utf-8', $headers)
        );
    }

    /**
     * Create the message body.
     *
     * @param string|StreamInterface $html
     * @throws InvalidArgumentException If $html is neither a string or stream.
     */
    private function createBody($html): StreamInterface {
        if ($html instanceof StreamInterface) {
            return $html;
        }

        /** @psalm-suppress DocblockTypeContradiction */
        if (! is_string($html)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid content (%s) provided to %s',
                get_debug_type($html),
                self::class
            ));
        }

        $body = new Stream('php://temp', 'wb+');
        $body->write($html);
        $body->rewind();
        return $body;
    }
}
