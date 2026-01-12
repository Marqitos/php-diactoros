<?php
/**
 * This file is part of the Rodas\Diactoros
 *
 * Based on Laminas\Diactoros\RequestTrait.php
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
use Rodas\Psr\Http\Message\RequestInterface;
use Rodas\Psr\Http\Message\RequestMethod;
use Rodas\Psr\Http\Message\StreamInterface;
use Rodas\Psr\Http\Message\UriInterface;

use function array_keys;
use function is_string;
use function preg_match;
use function sprintf;
use function strtolower;

/**
 * Trait with common request behaviors.
 *
 * Server and client-side requests differ slightly in how the Host header is
 * handled; on client-side, it should be calculated on-the-fly from the
 * composed URI (if present), while on server-side, it will be calculated from
 * the environment. As such, this trait exists to provide the common code
 * between both client-side and server-side requests, and each can then
 * use the headers functionality required by their implementations.
 */
trait RequestTrait {
    use MessageTrait;

    /**
     * Gets the HTTP method of the request.
     *
     * @var string
     */
    public private(set) string $method = 'GET' {
        get => $this->method;
        set => $this->method = $value;
    }

    /**
     * Gets the HTTP method of the request.
     *
     * @var RequestMethod|null Returns the request method.
     */
    public private(set) ?RequestMethod $requestMethod = RequestMethod::GET {
        get => $this->requestMethod;
        set => $this->requestMethod = $value;
    }

    /**
     * Gets the message's request target.
     *
     * Retrieves the message's request-target either as it will appear (for
     * clients), as it appeared at request (for servers), or as it was
     * specified for the instance (see withRequestTarget()).
     *
     * In most cases, this will be the origin-form of the composed URI,
     * unless a value was provided to the concrete implementation (see
     * withRequestTarget() below).
     *
     * If no URI is available, and no request-target has been specifically
     * provided, this method MUST return the string "/".
     *
     * @var string
     */
    public private(set) string $requestTarget {
        get {
            if (isset($this->requestTarget) &&
                null !== $this->requestTarget) {
                return $this->requestTarget;
            }

            $target = $this->uri->path;
            if ($this->uri->query) {
                $target .= '?' . $this->uri->query;
            }

            if (empty($target)) {
                $target = '/';
            }

            return $target;
        }
        set => $this->requestTarget = $value;
    }

    /**
     * Gets the URI instance.
     *
     * This method MUST return a UriInterface instance.
     *
     * @link https://tools.ietf.org/html/rfc3986#section-4.3
     * @var UriInterface Returns a UriInterface instance
     *     representing the URI of the request.
     */
    public private(set) UriInterface $uri {
        get => $this->uri;
        set => $this->uri = $value;
    }

    /**
     * Initialize request state.
     *
     * Used by constructors.
     *
     * @param null|string|UriInterface $uri URI for the request, if any.
     * @param null|string $method HTTP method for the request, if any.
     * @param string|resource|StreamInterface $body Message body, if any.
     * @param array<non-empty-string, string|string[]> $headers Headers for the message, if any.
     * @throws InvalidArgumentException For any invalid value.
     */
    private function initialize(
        UriInterface|string|null $uri = null,
        RequestMethod|string|null $method = null,
        $body = 'php://memory',
        array $headers = []
    ): void {
        if ($method !== null) {
            $this->setMethod($method);
        }

        $this->uri  = $this->createUri($uri);
        $this->body = $this->getStream($body, 'wb+');

        $this->setHeaders($headers);

        // per PSR-7: attempt to set the Host header from a provided URI if no
        // Host header is provided
        if (! $this->hasHeader('Host') &&
            $this->uri->host) {

            $this->headerNames['host']  = 'Host';
            $headers                    = $this->headers;
            $headers['Host']            = [$this->getHostFromUri()];
            $this->headers              = $headers;
        }
    }

    /**
     * Create and return a URI instance.
     *
     * If `$uri` is a already a `UriInterface` instance, returns it.
     *
     * If `$uri` is a string, passes it to the `Uri` constructor to return an
     * instance.
     *
     * If `$uri is null, creates and returns an empty `Uri` instance.
     *
     * Otherwise, it raises an exception.
     *
     * @throws InvalidArgumentException
     */
    private function createUri(UriInterface|string|null $uri): UriInterface {
        if ($uri instanceof UriInterface) {
            return $uri;
        }

        if (is_string($uri)) {
            return new Uri($uri);
        }

        return new Uri();
    }


    /**
     * Create a new instance with a specific request-target.
     *
     * If the request needs a non-origin-form request-target — e.g., for
     * specifying an absolute-form, authority-form, or asterisk-form —
     * this method may be used to create an instance with the specified
     * request-target, verbatim.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return a new instance that has the
     * changed request target.
     *
     * @link http://tools.ietf.org/html/rfc7230#section-2.7 (for the various
     *     request-target forms allowed in request messages)
     *
     * @throws InvalidArgumentException If the request target is invalid.
     * @return static
     */
    public function withRequestTarget(string $requestTarget): RequestInterface {
        if (preg_match('#\s#', $requestTarget)) {
            throw new InvalidArgumentException(
                'Invalid request target provided; cannot contain whitespace'
            );
        }

        $new                = clone $this;
        $new->requestTarget = $requestTarget;
        return $new;
    }

    /**
     * Return an instance with the provided HTTP method.
     *
     * While HTTP method names are typically all uppercase characters, HTTP
     * method names are case-sensitive and thus implementations SHOULD NOT
     * modify the given string.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * changed request method.
     *
     * @param string $method Case-insensitive method.
     * @throws InvalidArgumentException For invalid HTTP methods.
     * @return static
     */
    public function withMethod(RequestMethod|string $method): RequestInterface {
        $new = clone $this;
        $new->setMethod($method);
        return $new;
    }

    /**
     * Returns an instance with the provided URI.
     *
     * This method will update the Host header of the returned request by
     * default if the URI contains a host component. If the URI does not
     * contain a host component, any pre-existing Host header will be carried
     * over to the returned request.
     *
     * You can opt-in to preserving the original state of the Host header by
     * setting `$preserveHost` to `true`. When `$preserveHost` is set to
     * `true`, the returned request will not update the Host header of the
     * returned message -- even if the message contains no Host header. This
     * means that a call to `getHeader('Host')` on the original request MUST
     * equal the return value of a call to `getHeader('Host')` on the returned
     * request.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new UriInterface instance.
     *
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     *
     * @param UriInterface $uri New request URI to use.
     * @param bool $preserveHost Preserve the original state of the Host header.
     * @return static
     */
    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface {
        $new      = clone $this;
        $new->uri = $uri;

        if ($preserveHost && $this->hasHeader('Host')) {
            return $new;
        }

        if (! $uri->host) {
            return $new;
        }

        $host = $uri->host;
        if ($uri->port !== null) {
            $host .= ':' . $uri->port;
        }

        $new->headerNames['host'] = 'Host';

        // Remove an existing host header if present, regardless of current
        // de-normalization of the header name.
        // @see https://github.com/zendframework/zend-diactoros/issues/91
        $newHeaders = $new->headers;
        foreach (array_keys($newHeaders) as $header) {
            if (strtolower($header) === 'host') {
                unset($newHeaders[$header]);
            }
        }

        $newHeaders['Host'] = [$host];
        $new->headers = $newHeaders;
        return $new;
    }

    /**
     * Set and validate the HTTP method
     *
     * @throws InvalidArgumentException On invalid HTTP method.
     */
    private function setMethod(RequestMethod|string $method): void {
        if ($method instanceof RequestMethod) {
            $this->requestMethod = $method;
            $method = $method->value;
        } elseif (! preg_match('/^[!#$%&\'*+.^_`\|~0-9a-z-]+$/i', $method)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported HTTP method "%s" provided',
                $method
            ));
        }
        $this->method = $method;
        $this->requestMethod = RequestMethod::tryParse($method);
    }

    /**
     * Retrieve the host from the URI instance
     */
    private function getHostFromUri(): string {
        $host  = $this->uri->host;
        $host .= $this->uri->port !== null
            ? ':' . $this->uri->port
            : '';
        return $host;
    }
}
