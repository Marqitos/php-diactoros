<?php
/**
 * This file is part of the Rodas\Diactoros
 *
 * Based on Laminas\Diactoros\Response\ArraySerializer.php
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

use Rodas\Diactoros\Exception;
use Rodas\Diactoros\Response;
use Rodas\Diactoros\Stream;
use Rodas\Psr\Http\Message\ResponseInterface;
use Throwable;

use function sprintf;

/**
 * Serialize or deserialize response messages to/from arrays.
 *
 * This class provides functionality for serializing a ResponseInterface instance
 * to an array, as well as the reverse operation of creating a Response instance
 * from an array representing a message.
 */
final class ArraySerializer {
    /**
     * Serialize a response message to an array.
     *
     * @return array{
     *     status_code: int,
     *     reason_phrase: string,
     *     protocol_version: string,
     *     headers: array<array<string>>,
     *     body: string
     * }
     */
    public static function toArray(ResponseInterface $response): array {
        return [
            'status_code'      => $response->getStatusCode(),
            'reason_phrase'    => $response->getReasonPhrase(),
            'protocol_version' => $response->getProtocolVersion(),
            'headers'          => $response->getHeaders(),
            'body'             => (string) $response->getBody(),
        ];
    }

    /**
     * Deserialize a response array to a response instance.
     *
     * @throws Exception\DeserializationException When cannot deserialize response.
     */
    public static function fromArray(array $serializedResponse): Response {
        try {
            $body = new Stream('php://memory', 'wb+');
            $body->write(self::getValueFromKey($serializedResponse, 'body'));

            $statusCode      = self::getValueFromKey($serializedResponse, 'status_code');
            $headers         = self::getValueFromKey($serializedResponse, 'headers');
            $protocolVersion = self::getValueFromKey($serializedResponse, 'protocol_version');
            $reasonPhrase    = self::getValueFromKey($serializedResponse, 'reason_phrase');

            return (new Response($body, $statusCode, $headers))
                ->withProtocolVersion($protocolVersion)
                ->withStatus($statusCode, $reasonPhrase);
        } catch (Throwable $exception) {
            throw Exception\DeserializationException::forResponseFromArray($exception);
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
            $message = sprintf('Missing "%s" key in serialized response', $key);
        }
        throw new Exception\DeserializationException($message);
    }
}
