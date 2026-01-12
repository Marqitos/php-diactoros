<?php
/**
 * This file is part of the Rodas\Diactoros
 *
 * Based on Laminas\Diactoros\Request\ArraySerializer.php
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

namespace Rodas\Diactoros\Request;

use Rodas\Diactoros\Exception;
use Rodas\Diactoros\Request;
use Rodas\Diactoros\Stream;
use Rodas\Psr\Http\Message\RequestInterface;
use Throwable;

use function sprintf;

/**
 * Serialize or deserialize request messages to/from arrays.
 *
 * This class provides functionality for serializing a RequestInterface instance
 * to an array, as well as the reverse operation of creating a Request instance
 * from an array representing a message.
 */
final class ArraySerializer {
    /**
     * Serialize a request message to an array.
     *
     * @return array{
     *     method: string,
     *     request_target: string,
     *     uri: string,
     *     protocol_version: string,
     *     headers: array<array<string>>,
     *     body: string
     * }
     */
    public static function toArray(RequestInterface $request): array {
        return [
            'method'           => $request->method,
            'request_target'   => $request->requestTarget,
            'uri'              => (string) $request->uri,
            'protocol_version' => $request->protocolVersion,
            'headers'          => $request->headers,
            'body'             => (string) $request->body,
        ];
    }

    /**
     * Deserialize a request array to a request instance.
     *
     * @throws Exception\DeserializationException When the response cannot be deserialized.
     */
    public static function fromArray(array $serializedRequest): Request {
        try {
            $uri    = self::getValueFromKey($serializedRequest, 'uri');
            $method = self::getValueFromKey($serializedRequest, 'method');
            $body   = new Stream('php://memory', 'wb+');
            $body->write(self::getValueFromKey($serializedRequest, 'body'));
            $headers         = self::getValueFromKey($serializedRequest, 'headers');
            $requestTarget   = self::getValueFromKey($serializedRequest, 'request_target');
            $protocolVersion = self::getValueFromKey($serializedRequest, 'protocol_version');

            return (new Request($uri, $method, $body, $headers))
                ->withRequestTarget($requestTarget)
                ->withProtocolVersion($protocolVersion);
        } catch (Throwable $exception) {
            throw Exception\DeserializationException::forRequestFromArray($exception);
        }
    }

    /**
     * @throws Exception\DeserializationException
     */
    private static function getValueFromKey(array $data, string $key, ?string $message = null): mixed {
        if (isset($data[$key])) {
            return $data[$key];
        }
        if ($message === null) {
            $message = sprintf('Missing "%s" key in serialized request', $key);
        }
        throw new Exception\DeserializationException($message);
    }
}
