<?php

declare(strict_types=1);

namespace Rodas\Diactoros;

use Rodas\Diactoros\ServerRequestFilter\FilterServerRequestInterface;
use Rodas\Diactoros\ServerRequestFilter\FilterUsingXForwardedHeaders;
use Override;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_filter;
use function array_key_exists;
use function is_array;
use function is_callable;
use function is_string;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function rawurldecode;
use function str_starts_with;
use function strtolower;
use function strtr;
use function substr;

use const ARRAY_FILTER_USE_KEY;
use const PREG_SET_ORDER;

/**
 * Class for marshaling a request object from the current PHP environment.
 */
class ServerRequestFactory implements ServerRequestFactoryInterface {
    /**
     * Function to use to get apache request headers; present only to simplify mocking.
     *
     * @var callable|string
     */
    private static $apacheRequestHeaders = 'apache_request_headers';

    /**
     * Create an uploaded file instance from an array of values.
     *
     * @param array $spec A single $_FILES entry.
     * @throws InvalidArgumentException If one or more of the tmp_name,
     *     size, or error keys are missing from $spec.
     */
    static function createUploadedFile(array $spec): UploadedFile {
        if (! isset($spec['tmp_name']) ||
            ! isset($spec['size']) ||
            ! isset($spec['error'])) {
            throw new InvalidArgumentException(sprintf(
                '$spec provided to %s MUST contain each of the keys "tmp_name",'
                . ' "size", and "error"; one or more were missing',
                __FUNCTION__
            ));
        }

        return new UploadedFile(
            $spec['tmp_name'],
            (int) $spec['size'],
            $spec['error'],
            $spec['name'] ?? null,
            $spec['type'] ?? null
        );
    }

    /**
     * Create a request from the supplied superglobal values.
     *
     * If any argument is not supplied, the corresponding superglobal value will
     * be used.
     *
     * The ServerRequest created is then passed to the fromServer() method in
     * order to marshal the request URI and headers.
     *
     * @see fromServer()
     *
     * @param null|array $server $_SERVER superglobal
     * @param null|array $query $_GET superglobal
     * @param null|array $body $_POST superglobal
     * @param null|array $cookies $_COOKIE superglobal
     * @param null|array $files $_FILES superglobal
     * @param null|FilterServerRequestInterface $requestFilter If present, the
     *     generated request will be passed to this instance and the result
     *     returned by this method. When not present, a default instance of
     *     FilterUsingXForwardedHeaders is created, using the `trustReservedSubnets()`
     *     constructor.
     */
    public static function fromGlobals(
        ?array $server = null,
        ?array $query = null,
        ?array $body = null,
        ?array $cookies = null,
        ?array $files = null,
        ?FilterServerRequestInterface $requestFilter = null
    ): ServerRequestInterface {
        $requestFilter ??= FilterUsingXForwardedHeaders::trustReservedSubnets();

        $server  = static::normalizeServer(
            $server ?? $_SERVER,
            is_callable(self::$apacheRequestHeaders) ? self::$apacheRequestHeaders : null
        );
        $files   = static::normalizeUploadedFiles($files ?? $_FILES);
        $headers = static::marshalHeadersFromSapi($server);

        if (null === $cookies && array_key_exists('cookie', $headers)) {
            $cookies = static::parseCookieHeader($headers['cookie']);
        }

        return $requestFilter(new ServerRequest(
            $server,
            $files,
            UriFactory::createFromSapi($server, $headers),
            static::marshalMethodFromSapi($server),
            'php://input',
            $headers,
            $cookies ?? $_COOKIE,
            $query ?? $_GET,
            $body ?? $_POST,
            static::marshalProtocolVersionFromSapi($server)
        ));
    }

    /**
     * @param array $server Values obtained from the SAPI (generally `$_SERVER`).
     * @return array<non-empty-string, mixed> Header/value pairs
     */
    static function marshalHeadersFromSapi(array $server): array {
        $contentHeaderLookup = isset($server['LAMINAS_DIACTOROS_STRICT_CONTENT_HEADER_LOOKUP'])
            ? static function (string $key): bool {
                static $contentHeaders = [
                    'CONTENT_TYPE'   => true,
                    'CONTENT_LENGTH' => true,
                    'CONTENT_MD5'    => true,
                ];
                return isset($contentHeaders[$key]);
            }
            : static fn(string $key): bool => str_starts_with($key, 'CONTENT_');

        $headers = [];
        foreach ($server as $key => $value) {
            if (! is_string($key) ||
                $key === '') {

                continue;
            }

            if ($value === '') {
                continue;
            }

            // Apache prefixes environment variables with REDIRECT_
            // if they are added by rewrite rules
            if (str_starts_with($key, 'REDIRECT_')) {
                $key = substr($key, 9);

                // We will not overwrite existing variables with the
                // prefixed versions, though
                if (array_key_exists($key, $server)) {
                    continue;
                }
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name           = strtr(strtolower(substr($key, 5)), '_', '-');
                $headers[$name] = $value;
                continue;
            }

            if ($contentHeaderLookup($key)) {
                $name           = strtr(strtolower($key), '_', '-');
                $headers[$name] = $value;
            }
        }

        // Filter out integer keys.
        // These can occur if the translated header name is a string integer.
        // PHP will cast those to integers when assigned to an array.
        // This filters them out.
        return array_filter($headers, fn(string|int $key): bool => is_string($key), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Retrieve the request method from the SAPI parameters.
     */
    static function marshalMethodFromSapi(array $server): string {
        return $server['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Return HTTP protocol version (X.Y) as discovered within a `$_SERVER` array.
     *
     * @throws Exception\UnrecognizedProtocolVersionException If the
     *     $server['SERVER_PROTOCOL'] value is malformed.
     */
    static function marshalProtocolVersionFromSapi(array $server): string {
        if (! isset($server['SERVER_PROTOCOL'])) {
            return '1.1';
        }

        if (! preg_match('#^(HTTP/)?(?P<version>[1-9]\d*(?:\.\d)?)$#', $server['SERVER_PROTOCOL'], $matches)) {
            throw Exception\UnrecognizedProtocolVersionException::forVersion(
                (string) $server['SERVER_PROTOCOL']
            );
        }

        return $matches['version'];
    }

    /**
     * Marshal the $_SERVER array
     *
     * Pre-processes and returns the $_SERVER superglobal. In particularly, it
     * attempts to detect the Authorization header, which is often not aggregated
     * correctly under various SAPI/httpd combinations.
     *
     * @param null|callable $apacheRequestHeaderCallback Callback that can be used to
     *     retrieve Apache request headers. This defaults to
     *     `apache_request_headers` under the Apache mod_php.
     * @return array Either $server verbatim, or with an added HTTP_AUTHORIZATION header.
     */
    static function normalizeServer(array $server, ?callable $apacheRequestHeaderCallback = null): array {
        if (null === $apacheRequestHeaderCallback &&
            is_callable('apache_request_headers')) {

            $apacheRequestHeaderCallback = 'apache_request_headers';
        }

        // If the HTTP_AUTHORIZATION value is already set, or the callback is not
        // callable, we return verbatim
        if (isset($server['HTTP_AUTHORIZATION']) ||
            ! is_callable($apacheRequestHeaderCallback)) {

            return $server;
        }

        $apacheRequestHeaders = $apacheRequestHeaderCallback();
        if (isset($apacheRequestHeaders['Authorization'])) {
            $server['HTTP_AUTHORIZATION'] = $apacheRequestHeaders['Authorization'];
            return $server;
        }

        if (isset($apacheRequestHeaders['authorization'])) {
            $server['HTTP_AUTHORIZATION'] = $apacheRequestHeaders['authorization'];
            return $server;
        }

        return $server;
    }

    /**
     * Normalize uploaded files
     *
     * Transforms each value into an UploadedFile instance, and ensures that nested
     * arrays are normalized.
     *
     * @return UploadedFileInterface[]
     * @throws InvalidArgumentException For unrecognized values.
     */
    static function normalizeUploadedFiles(array $files): array {
        /**
         * Traverse a nested tree of uploaded file specifications.
         *
         * @param string[]|array[] $tmpNameTree
         * @param int[]|array[] $sizeTree
         * @param int[]|array[] $errorTree
         * @param string[]|array[]|null $nameTree
         * @param string[]|array[]|null $typeTree
         * @return UploadedFile[]|array[]
         */
        $recursiveNormalize = static function (
            array $tmpNameTree,
            array $sizeTree,
            array $errorTree,
            ?array $nameTree = null,
            ?array $typeTree = null
        ) use (&$recursiveNormalize): array {
            $normalized = [];
            foreach ($tmpNameTree as $key => $value) {
                if (is_array($value)) {
                    // Traverse
                    $normalized[$key] = $recursiveNormalize(
                        $tmpNameTree[$key],
                        $sizeTree[$key],
                        $errorTree[$key],
                        $nameTree[$key] ?? null,
                        $typeTree[$key] ?? null
                    );
                    continue;
                }
                $normalized[$key] = static::createUploadedFile([
                    'tmp_name' => $tmpNameTree[$key],
                    'size'     => $sizeTree[$key],
                    'error'    => $errorTree[$key],
                    'name'     => $nameTree[$key] ?? null,
                    'type'     => $typeTree[$key] ?? null,
                ]);
            }
            return $normalized;
        };

        /**
         * Normalize an array of file specifications.
         *
         * Loops through all nested files (as determined by receiving an array to the
         * `tmp_name` key of a `$_FILES` specification) and returns a normalized array
         * of UploadedFile instances.
         *
         * This function normalizes a `$_FILES` array representing a nested set of
         * uploaded files as produced by the php-fpm SAPI, CGI SAPI, or mod_php
         * SAPI.
         *
         * @param array $files
         * @return UploadedFile[]
         */
        $normalizeUploadedFileSpecification = static function (array $files = []) use (&$recursiveNormalize): array {
            if (
                ! isset($files['tmp_name']) || ! is_array($files['tmp_name'])
                || ! isset($files['size']) || ! is_array($files['size'])
                || ! isset($files['error']) || ! is_array($files['error'])
            ) {
                throw new InvalidArgumentException(sprintf(
                    '$files provided to %s MUST contain each of the keys "tmp_name",'
                    . ' "size", and "error", with each represented as an array;'
                    . ' one or more were missing or non-array values',
                    __FUNCTION__
                ));
            }

            return $recursiveNormalize(
                $files['tmp_name'],
                $files['size'],
                $files['error'],
                $files['name'] ?? null,
                $files['type'] ?? null
            );
        };

        $normalized = [];
        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $normalized[$key] = $value;
                continue;
            }

            if (is_array($value) && isset($value['tmp_name']) && is_array($value['tmp_name'])) {
                $normalized[$key] = $normalizeUploadedFileSpecification($value);
                continue;
            }

            if (is_array($value) && isset($value['tmp_name'])) {
                $normalized[$key] = static::createUploadedFile($value);
                continue;
            }

            if (is_array($value)) {
                $normalized[$key] = static::normalizeUploadedFiles($value);
                continue;
            }

            throw new InvalidArgumentException('Invalid value in files specification');
        }
        return $normalized;
    }

    /**
     * Parse a cookie header according to RFC 6265.
     *
     * PHP will replace special characters in cookie names, which results in other cookies not being available due to
     * overwriting. Thus, the server request should take the cookies from the request header instead.
     *
     * @param string $cookieHeader A string cookie header value.
     * @return array<non-empty-string, string> key/value cookie pairs.
     */
    static function parseCookieHeader($cookieHeader): array {
        preg_match_all('(
            (?:^\\n?[ \t]*|;[ ])
            (?P<name>[!#$%&\'*+-.0-9A-Z^_`a-z|~]+)
            =
            (?P<DQUOTE>"?)
                (?P<value>[\x21\x23-\x2b\x2d-\x3a\x3c-\x5b\x5d-\x7e]*)
            (?P=DQUOTE)
            (?=\\n?[ \t]*$|;[ ])
        )x', $cookieHeader, $matches, PREG_SET_ORDER);

        $cookies = [];

        foreach ($matches as $match) {
            $cookies[$match['name']] = rawurldecode($match['value']);
        }

        return $cookies;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        $uploadedFiles = [];

        return new ServerRequest(
            $serverParams,
            $uploadedFiles,
            $uri,
            $method,
            'php://temp'
        );
    }
}
