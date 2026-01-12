<?php

declare(strict_types=1);

namespace Rodas\Test\Diactoros\Response;

use Rodas\Diactoros\Response;
use Rodas\Diactoros\Response\EmptyResponse;
use Rodas\Psr\Http\Message\StatusCode;
use PHPUnit\Framework\TestCase;

final class EmptyResponseTest extends TestCase {
    public function testConstructor(): void {
        $response = new EmptyResponse(201);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('', (string) $response->body);
        $this->assertSame(201, $response->status);
        $this->assertSame(StatusCode::CREATED, $response->statusCode);
    }

    public function testHeaderConstructor(): void {
        $response = EmptyResponse::withHeaders(['x-empty' => ['true']]);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('', (string) $response->body);
        $this->assertSame(204, $response->status);
        $this->assertSame(StatusCode::NO_CONTENT, $response->statusCode);
        $this->assertSame('true', $response->getHeaderLine('x-empty'));
    }
}
