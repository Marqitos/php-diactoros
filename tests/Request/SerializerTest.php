<?php

declare(strict_types=1);

namespace Rodas\Test\Diactoros\Request;

use InvalidArgumentException;
use Rodas\Diactoros\RelativeStream;
use Rodas\Diactoros\Request;
use Rodas\Diactoros\Request\Serializer;
use Rodas\Diactoros\Stream;
use Rodas\Diactoros\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Runtime\PropertyHook;
use PHPUnit\Framework\TestCase;
use Rodas\Psr\Http\Message\RequestInterface;
use Rodas\Psr\Http\Message\RequestMethod;
use Rodas\Psr\Http\Message\StreamInterface;
use UnexpectedValueException;

use function json_encode;
use function strlen;

use const JSON_THROW_ON_ERROR;

final class SerializerTest extends TestCase
{
    public function testSerializesBasicRequest(): void
    {
        $request = (new Request())
            ->withMethod('GET')
            ->withUri(new Uri('http://example.com/foo/bar?baz=bat'))
            ->withAddedHeader('Accept', 'text/html');

        $message = Serializer::toString($request);
        $this->assertSame(
            "GET /foo/bar?baz=bat HTTP/1.1\r\nHost: example.com\r\nAccept: text/html",
            $message,
        );
    }

    public function testSerializesRequestWithBody(): void
    {
        $body   = json_encode(['test' => 'value'], JSON_THROW_ON_ERROR);
        $stream = new Stream('php://memory', 'wb+');
        $stream->write($body);

        $request = (new Request())
            ->withMethod('POST')
            ->withUri(new Uri('http://example.com/foo/bar'))
            ->withAddedHeader('Accept', 'application/json')
            ->withAddedHeader('Content-Type', 'application/json')
            ->withBody($stream);

        $message = Serializer::toString($request);
        $this->assertStringContainsString("POST /foo/bar HTTP/1.1\r\n", $message);
        $this->assertStringContainsString("\r\n\r\n" . $body, $message);
    }

    public function testSerializesMultipleHeadersCorrectly(): void
    {
        $request = (new Request())
            ->withMethod('GET')
            ->withUri(new Uri('http://example.com/foo/bar?baz=bat'))
            ->withAddedHeader('X-Foo-Bar', 'Baz')
            ->withAddedHeader('X-Foo-Bar', 'Bat');

        $message = Serializer::toString($request);
        $this->assertStringContainsString("X-Foo-Bar: Baz", $message);
        $this->assertStringContainsString("X-Foo-Bar: Bat", $message);
    }

    /** @return non-empty-array<non-empty-string, array{non-empty-string, non-empty-string, array<non-empty-string, non-empty-string>}> */
    public static function originForms(): array
    {
        return [
            'path-only'      => [
                'GET /foo HTTP/1.1',
                '/foo',
                ['path' => '/foo'],
            ],
            'path-and-query' => [
                'GET /foo?bar HTTP/1.1',
                '/foo?bar',
                ['path' => '/foo', 'query' => 'bar'],
            ],
        ];
    }

    /**
     * @param non-empty-string                          $line
     * @param non-empty-string                          $requestTarget
     * @param array<non-empty-string, non-empty-string> $expectations
     */
    #[DataProvider('originForms')]
    public function testCanDeserializeRequestWithOriginForm(
        string $line,
        string $requestTarget,
        array $expectations
    ): void {
        $message = $line . "\r\nX-Foo-Bar: Baz\r\n\r\nContent";
        $request = Serializer::fromString($message);

        $this->assertSame('GET', $request->method);
        $this->assertSame(RequestMethod::GET, $request->requestMethod);
        $this->assertSame($requestTarget, $request->requestTarget);

        $uri = $request->uri;
        foreach ($expectations as $property => $expect) {
            $this->assertSame($expect, $uri->{$property});
        }
    }

    /**
     * @return non-empty-array<
     *     non-empty-string,
     *     array{
     *         non-empty-string,
     *         non-empty-string,
     *         array{
     *             getScheme?: non-empty-string,
     *             getUserInfo?: non-empty-string,
     *             getHost?: non-empty-string,
     *             getPort?: positive-int,
     *             getPath?: non-empty-string,
     *             getQuery?: non-empty-string
     *         }
     *     }
     * >
     */
    public static function absoluteForms(): array
    {
        return [
            'path-only'      => [
                'GET http://example.com/foo HTTP/1.1',
                'http://example.com/foo',
                [
                    'scheme' => 'http',
                    'host'   => 'example.com',
                    'path'   => '/foo',
                ],
            ],
            'path-and-query' => [
                'GET http://example.com/foo?bar HTTP/1.1',
                'http://example.com/foo?bar',
                [
                    'scheme' => 'http',
                    'host'   => 'example.com',
                    'path'   => '/foo',
                    'query'  => 'bar',
                ],
            ],
            'with-port'      => [
                'GET http://example.com:8080/foo?bar HTTP/1.1',
                'http://example.com:8080/foo?bar',
                [
                    'scheme' => 'http',
                    'host'   => 'example.com',
                    'port'   => 8080,
                    'path'   => '/foo',
                    'query'  => 'bar',
                ],
            ],
            'with-authority' => [
                'GET https://me:too@example.com:8080/foo?bar HTTP/1.1',
                'https://me:too@example.com:8080/foo?bar',
                [
                    'scheme'   => 'https',
                    'userInfo' => 'me:too',
                    'host'     => 'example.com',
                    'port'     => 8080,
                    'path'     => '/foo',
                    'query'    => 'bar',
                ],
            ],
        ];
    }

    // @codingStandardsIgnoreStart if we split these line, phpcs can't associate parameter name and docblock anymore (phpcs limitation)
    /**
     * @param non-empty-string $line
     * @param non-empty-string $requestTarget
     * @param array{getScheme?: non-empty-string, getUserInfo?: non-empty-string, getHost?: non-empty-string, getPort?: positive-int, getPath?: non-empty-string, getQuery?: non-empty-string} $expectations
     */
    #[DataProvider('absoluteForms')]
    public function testCanDeserializeRequestWithAbsoluteForm(
        string $line,
        string $requestTarget,
        array $expectations
    ): void {
        // @codingStandardsIgnoreEnd
        $message = $line . "\r\nX-Foo-Bar: Baz\r\n\r\nContent";
        $request = Serializer::fromString($message);

        $this->assertSame('GET', $request->method);

        $this->assertSame($requestTarget, $request->requestTarget);

        $uri = $request->uri;
        foreach ($expectations as $property => $expect) {
            $this->assertSame($expect, $uri->{$property});
        }
    }

    public function testCanDeserializeRequestWithAuthorityForm(): void {
        $message = "CONNECT www.example.com:80 HTTP/1.1\r\nX-Foo-Bar: Baz";
        $request = Serializer::fromString($message);
        $this->assertSame('CONNECT', $request->method);
        $this->assertSame('www.example.com:80', $request->requestTarget);

        $uri = $request->uri;
        $this->assertNotSame('www.example.com', $uri->host);
        $this->assertNotSame(80, $uri->port);
    }

    public function testCanDeserializeRequestWithAsteriskForm(): void {
        $message = "OPTIONS * HTTP/1.1\r\nHost: www.example.com";
        $request = Serializer::fromString($message);
        $this->assertSame('OPTIONS', $request->method);
        $this->assertSame('*', $request->requestTarget);

        $uri = $request->uri;
        $this->assertNotSame('www.example.com', $uri->host);

        $this->assertTrue($request->hasHeader('Host'));
        $this->assertSame('www.example.com', $request->getHeaderLine('Host'));
    }

    /** @return non-empty-array<non-empty-string, array{non-empty-string}> */
    public static function invalidRequestLines(): array
    {
        return [
            'missing-method'   => ['/foo/bar HTTP/1.1'],
            'missing-target'   => ['GET HTTP/1.1'],
            'missing-protocol' => ['GET /foo/bar'],
            'simply-malformed' => ['What is this mess?'],
        ];
    }

    /**
     * @param non-empty-string $line
     */
    #[DataProvider('invalidRequestLines')]
    public function testRaisesExceptionDuringDeserializationForInvalidRequestLine(string $line): void
    {
        $message = $line . "\r\nX-Foo-Bar: Baz\r\n\r\nContent";

        $this->expectException(UnexpectedValueException::class);

        Serializer::fromString($message);
    }

    public function testCanDeserializeRequestWithMultipleHeadersOfSameName(): void
    {
        $text    = "POST /foo HTTP/1.0\r\nContent-Type: text/plain\r\nX-Foo-Bar: Baz\r\nX-Foo-Bar: Bat\r\n\r\nContent!";
        $request = Serializer::fromString($text);

        $this->assertInstanceOf(RequestInterface::class, $request);
        $this->assertInstanceOf(Request::class, $request);

        $this->assertTrue($request->hasHeader('X-Foo-Bar'));
        $values = $request->getHeader('X-Foo-Bar');
        $this->assertSame(['Baz', 'Bat'], $values);
    }

    /** @return non-empty-array<non-empty-string, array{non-empty-string}> */
    public static function headersWithContinuationLines(): array
    {
        return [
            'space' => ["POST /foo HTTP/1.0\r\nContent-Type: text/plain\r\nX-Foo-Bar: Baz;\r\n Bat\r\n\r\nContent!"],
            'tab'   => ["POST /foo HTTP/1.0\r\nContent-Type: text/plain\r\nX-Foo-Bar: Baz;\r\n\tBat\r\n\r\nContent!"],
        ];
    }

    /**
     * @param non-empty-string $text
     */
    #[DataProvider('headersWithContinuationLines')]
    public function testCanDeserializeRequestWithHeaderContinuations(string $text): void
    {
        $request = Serializer::fromString($text);

        $this->assertInstanceOf(RequestInterface::class, $request);
        $this->assertInstanceOf(Request::class, $request);

        $this->assertTrue($request->hasHeader('X-Foo-Bar'));
        $this->assertSame('Baz; Bat', $request->getHeaderLine('X-Foo-Bar'));
    }

    /** @return non-empty-array<non-empty-string, array{non-empty-string}> */
    public static function headersWithWhitespace(): array
    {
        return [
            'no'       => ["POST /foo HTTP/1.0\r\nContent-Type: text/plain\r\nX-Foo-Bar:Baz\r\n\r\nContent!"],
            'leading'  => ["POST /foo HTTP/1.0\r\nContent-Type: text/plain\r\nX-Foo-Bar: Baz\r\n\r\nContent!"],
            'trailing' => ["POST /foo HTTP/1.0\r\nContent-Type: text/plain\r\nX-Foo-Bar:Baz \r\n\r\nContent!"],
            'both'     => ["POST /foo HTTP/1.0\r\nContent-Type: text/plain\r\nX-Foo-Bar: Baz \r\n\r\nContent!"],
            'mixed'    => ["POST /foo HTTP/1.0\r\nContent-Type: text/plain\r\nX-Foo-Bar: \t Baz\t \t\r\n\r\nContent!"],
        ];
    }

    #[DataProvider('headersWithWhitespace')]
    public function testDeserializationRemovesWhitespaceAroundValues(string $text): void
    {
        $request = Serializer::fromString($text);

        $this->assertInstanceOf(Request::class, $request);

        $this->assertSame('Baz', $request->getHeaderLine('X-Foo-Bar'));
    }

    /** @return non-empty-array<non-empty-string, array{non-empty-string, non-empty-string}> */
    public static function messagesWithInvalidHeaders(): array
    {
        return [
            'invalid-name'         => [
                "GET /foo HTTP/1.1\r\nThi;-I()-Invalid: value",
                'Invalid header detected',
            ],
            'invalid-format'       => [
                "POST /foo HTTP/1.1\r\nThis is not a header\r\n\r\nContent",
                'Invalid header detected',
            ],
            'invalid-continuation' => [
                "POST /foo HTTP/1.1\r\nX-Foo-Bar: Baz\r\nInvalid continuation\r\nContent",
                'Invalid header continuation',
            ],
        ];
    }

    /**
     * @param non-empty-string $message
     * @param non-empty-string $exceptionMessage
     */
    #[DataProvider('messagesWithInvalidHeaders')]
    public function testDeserializationRaisesExceptionForMalformedHeaders(
        string $message,
        string $exceptionMessage
    ): void {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($exceptionMessage);

        Serializer::fromString($message);
    }

    public function testFromStreamThrowsExceptionWhenStreamIsNotReadable(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream
            ->expects($this->once())
            ->method(PropertyHook::get('isReadable'))
            ->willReturn(false);

        $this->expectException(InvalidArgumentException::class);

        Serializer::fromStream($stream);
    }

    public function testFromStreamThrowsExceptionWhenStreamIsNotSeekable(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream
            ->expects($this->once())
            ->method(PropertyHook::get('isReadable'))
            ->willReturn(true);
        $stream
            ->expects($this->once())
            ->method(PropertyHook::get('isSeekable'))
            ->willReturn(false);

        $this->expectException(InvalidArgumentException::class);

        Serializer::fromStream($stream);
    }

    public function testFromStreamStopsReadingAfterScanningHeader(): void
    {
        $headers = "POST /foo HTTP/1.0\r\nContent-Type: text/plain\r\nX-Foo-Bar: Baz;\r\n Bat\r\n\r\n";
        $payload = $headers . "Content!";

        $stream = $this->createMock(StreamInterface::class);
        $stream
            ->expects($this->once())
            ->method(PropertyHook::get('isReadable'))
            ->willReturn(true);
        $stream
            ->expects($this->once())
            ->method(PropertyHook::get('isSeekable'))
            ->willReturn(true);

        // assert that full request body is not read, and returned as RelativeStream instead
        $stream->expects($this->exactly(strlen($headers)))
            ->method('read')
            ->with(1)
            ->willReturnCallback(static function () use ($payload) {
                static $i = 0;
                return $payload[$i++];
            });

        $stream = Serializer::fromStream($stream);

        $this->assertInstanceOf(RelativeStream::class, $stream->body);
    }
}
