<?php

declare(strict_types=1);

namespace Rodas\Test\Diactoros\ServerRequestFilter;

use Rodas\Diactoros\ServerRequest;
use Rodas\Diactoros\ServerRequestFilter\DoNotFilter;
use PHPUnit\Framework\TestCase;

final class DoNotFilterTest extends TestCase
{
    public function testReturnsSameInstanceItWasProvided(): void
    {
        $request = new ServerRequest();
        $filter  = new DoNotFilter();

        $this->assertSame($request, $filter($request));
    }
}
