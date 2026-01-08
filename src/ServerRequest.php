<?php
/**
 * This file is part of the Rodas\Diactoros
 *
 * Based on Laminas\Diactoros\ServerRequest.php
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
use Rodas\Psr\Http\Message\ServerRequestInterface;
use Rodas\Psr\Http\Message\StreamInterface;
use Rodas\Psr\Http\Message\UploadedFileInterface;
use Rodas\Psr\Http\Message\UriInterface;

use function array_key_exists;
use function gettype;
use function is_array;
use function is_object;
use function sprintf;

/**
 * Server-side HTTP request
 *
 * Extends the Request definition to add methods for accessing incoming data,
 * specifically server parameters, cookies, matched path parameters, query
 * string arguments, body parameters, and upload file information.
 *
 * "Attributes" are discovered via decomposing the request (and usually
 * specifically the URI path), and typically will be injected by the application.
 *
 * Requests are considered immutable; all methods that might change state are
 * implemented such that they retain the internal state of the current
 * message and return a new instance that contains the changed state.
 */
class ServerRequest implements ServerRequestInterface {
    use RequestTrait;

    /**
     * {@inheritdoc}
     */
    public private(set) array $attributes = [] {
        get => $this->attributes;
        set => $this->attributes = $value;
    }

    /**
     * {@inheritdoc}
     */
    public private(set) array $cookieParams = [] {
        get => $this->cookieParams;
        set => $this->cookieParams = $value;
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
                $this->uri->getHost()) {
                $headers['Host'] = [$this->getHostFromUri()];
            }

            return $headers;
        }
        set => $this->headers = $value;
    }

    /**
     * {@inheritdoc}
     */
    public private(set) array $queryParams = [] {
        get => $this->queryParams;
        set => $this->queryParams = $value;
    }

    /**
     * {@inheritdoc}
     */
    public private(set) array $serverParams = [] {
        get => $this->serverParams;
        set => $this->serverParams = $value;
    }

    /**
     * {@inheritdoc}
     */
    public private(set) array $uploadedFiles;

    /**
     * @param array $serverParams Server parameters, typically from $_SERVER
     * @param array $uploadedFiles Upload file information, a tree of UploadedFiles
     * @param null|string|UriInterface $uri URI for the request, if any.
     * @param null|string $method HTTP method for the request, if any.
     * @param string|resource|StreamInterface $body Message body, if any.
     * @param array<non-empty-string, string|string[]> $headers Headers for the message, if any.
     * @param array $cookieParams Cookies for the message, if any.
     * @param array $queryParams Query params for the message, if any.
     * @param null|array|object $parsedBody The deserialized body parameters, if any.
     * @param string $protocol HTTP protocol version.
     * @throws InvalidArgumentException For any invalid value.
     */
    public function __construct(
        array $serverParams = [],
        array $uploadedFiles = [],
        null|string|UriInterface $uri = null,
        RequestMethod|string|null $method = null,
        $body = 'php://input',
        array $headers = [],
        array $cookieParams = [],
        array $queryParams = [],
        private $parsedBody = null,
        string $protocol = '1.1'
    ) {
        $this->validateUploadedFiles($uploadedFiles);

        if ($body === 'php://input') {
            $body = new Stream($body, 'r');
        }

        $this->initialize($uri, $method, $body, $headers);
        $this->cookieParams  = $cookieParams;
        $this->uploadedFiles = $uploadedFiles;
        $this->serverParams  = $serverParams;
        $this->protocol      = $protocol;
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function withUploadedFiles(array $uploadedFiles): ServerRequest {
        $this->validateUploadedFiles($uploadedFiles);
        $new                = clone $this;
        $new->uploadedFiles = $uploadedFiles;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function withCookieParams(array $cookies): ServerRequest {
        $new               = clone $this;
        $new->cookieParams = $cookies;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function withQueryParams(array $query): ServerRequest {
        $new              = clone $this;
        $new->queryParams = $query;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function getParsedBody() {
        return $this->parsedBody;
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function withParsedBody($data): ServerRequest {
        /** @psalm-suppress DocblockTypeContradiction */
        if (! is_array($data) && ! is_object($data) && null !== $data) {
            throw new InvalidArgumentException(sprintf(
                '%s expects a null, array, or object argument; received %s',
                __METHOD__,
                gettype($data)
            ));
        }

        $new             = clone $this;
        $new->parsedBody = $data;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function getAttribute(string $name, $default = null) {
        if (! array_key_exists($name, $this->attributes)) {
            return $default;
        }

        return $this->attributes[$name];
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function withAttribute(string $name, $value): ServerRequest {
        $new                    = clone $this;
        $new->attributes[$name] = $value;
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    #[Override]
    public function withoutAttribute(string $name): ServerRequest {
        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }

    /**
     * Recursively validate the structure in an uploaded files array.
     *
     * @throws InvalidArgumentException If any leaf is not an UploadedFileInterface instance.
     */
    private function validateUploadedFiles(array $uploadedFiles): void {
        foreach ($uploadedFiles as $file) {
            if (is_array($file)) {
                $this->validateUploadedFiles($file);
                continue;
            }

            if (! $file instanceof UploadedFileInterface) {
                throw new InvalidArgumentException('Invalid leaf in uploaded files structure');
            }
        }
    }
}
