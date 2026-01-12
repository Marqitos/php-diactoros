<?php
/**
 * This file is part of the Rodas\Diactoros
 *
 * Based on Laminas\Diactoros\Response.php
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
use Rodas\Psr\Http\Message\ResponseInterface;
use Rodas\Psr\Http\Message\StatusCode;
use Rodas\Psr\Http\Message\StreamInterface;

use function sprintf;

/**
 * HTTP response encapsulation.
 *
 * Responses are considered immutable; all methods that might change state are
 * implemented such that they retain the internal state of the current
 * message and return a new instance that contains the changed state.
 */
class Response implements ResponseInterface {
    use MessageTrait;

    public const MIN_STATUS_CODE_VALUE = 100;
    public const MAX_STATUS_CODE_VALUE = 599;

    // TODO: Use Enum from Rodas/Psr
    /**
     * Map of standard HTTP status code/reason phrases
     *
     * @psalm-var array<positive-int, non-empty-string>
     */
    private array $phrases = [
        // INFORMATIONAL CODES
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        // phpcs:ignore Generic.Files.LineLength.TooLong
        104 => 'Upload Resumption Supported (TEMPORARY - registered 2024-11-13, extension registered 2025-09-15, expires 2026-11-13)',
        // SUCCESS CODES
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        // REDIRECTION CODES
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy', // Deprecated to 306 => '(Unused)'
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        // CLIENT ERROR
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Content Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Content',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        444 => 'Connection Closed Without Response',
        451 => 'Unavailable For Legal Reasons',
        // SERVER ERROR
        499 => 'Client Closed Request',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended (OBSOLETED)',
        511 => 'Network Authentication Required',
        599 => 'Network Connect Timeout Error',
    ];

    /**
     * Retrieves all message headers.
     *
     * The keys represent the header name as it will be sent over the wire, and
     * each value is an array of strings associated with the header.
     *
     *     // Represent the headers as a string
     *     foreach ($message->headers as $name => $values) {
     *         echo $name . ": " . implode(", ", $values);
     *     }
     *
     *     // Emit headers iteratively:
     *     foreach ($message->headers as $name => $values) {
     *         foreach ($values as $value) {
     *             header(sprintf('%s: %s', $name, $value), false);
     *         }
     *     }
     *
     * @return array Returns an associative array of the message's headers. Each
     *     key MUST be a header name, and each value MUST be an array of strings.
     * @psalm-return array<non-empty-string, list<string>>
     */
    public array $headers {
        get => $this->headers;
        set => $this->headers = $value;
    }

    /**
     * {@inheritdoc}
     */
    public private(set) string $reasonPhrase {
        get => $this->reasonPhrase;
        set => $this->reasonPhrase = $value;
    }

    public private(set) int $status = 200 {
        get => $this->status;
        set(int $value) {
            if ($value < static::MIN_STATUS_CODE_VALUE ||
                $value > static::MAX_STATUS_CODE_VALUE) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid status code "%s"; must be an integer between %d and %d, inclusive',
                    $value,
                    self::MIN_STATUS_CODE_VALUE,
                    self::MAX_STATUS_CODE_VALUE
                ));
            } else {
                $statusCode = StatusCode::tryFrom($value);
                if ($this->statusCode !== $statusCode) {

                    $this->statusCode = $statusCode;
                }
            }
            $this->status = $value;
        }
    }

    /**
     * {@inheritdoc}
     */
    public private(set) ?StatusCode $statusCode = StatusCode::OK {
        get => $this->statusCode;
        set(?StatusCode $value)  {
            $this->statusCode   = $value;
            if ($value !== null) {
                $code               = $value->value;
                if ($this->status !== $code) {
                    $this->status = $code;
                }
            }
        }
    }

    /**
     * @param string|resource|StreamInterface $body Stream identifier and/or actual stream resource
     * @param int $status Status code for the response, if any.
     * @param array<non-empty-string, string|string[]> $headers Headers for the response, if any.
     * @throws InvalidArgumentException On any invalid element.
     */
    public function __construct($body = 'php://memory', int $status = 200, array $headers = []) {
        $this->setStatusCode($status);
        $this->body = $this->getStream($body, 'wb+');
        $this->setHeaders($headers);
    }



    /**
     * {@inheritdoc}
     */
    #[Override]
    public function withStatus(StatusCode|int $code, string $reasonPhrase = ''): Response {
        $new = clone $this;
        $new->setStatusCode($code, $reasonPhrase);
        return $new;
    }

    /**
     * Set a valid status code.
     *
     * @throws InvalidArgumentException On an invalid status code.
     */
    private function setStatusCode(StatusCode|int $code, string $reasonPhrase = ''): void {
        if (is_int($code)) {
            $this->status = $code;
        } else {
            $this->statusCode = $code;
        }

        if ($reasonPhrase === '' &&
            isset($this->phrases[$code])) {

            $reasonPhrase = $this->phrases[$code];
        }

        $this->reasonPhrase = $reasonPhrase;
    }
}
