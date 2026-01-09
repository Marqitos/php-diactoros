<?php
/**
 * This file is part of the Rodas\Diactoros
 *
 * Based on Laminas\Diactoros\StreamFactory.php
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

use Override;
use Rodas\Psr\Http\Message\StreamFactoryInterface;
use Rodas\Psr\Http\Message\StreamInterface;

use function assert;
use function fopen;
use function fwrite;
use function is_resource;
use function rewind;

class StreamFactory implements StreamFactoryInterface {
    /**
     * {@inheritDoc}
     */
    #[Override]
    public function createStream(string $content = ''): StreamInterface {
        $resource = fopen('php://temp', 'r+');
        assert(is_resource($resource), 'Something is really wrong if PHP failed to open stream in memory');
        fwrite($resource, $content);
        rewind($resource);

        return $this->createStreamFromResource($resource);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface {
        return new Stream($filename, $mode);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function createStreamFromResource($resource): StreamInterface {
        return new Stream($resource);
    }
}
