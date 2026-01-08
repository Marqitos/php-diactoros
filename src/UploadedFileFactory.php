<?php
/**
 * This file is part of the Rodas\Diactoros
 *
 * Based on Laminas\Diactoros\UploadedFileFactory.php
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
use Rodas\Psr\Http\Message\StreamInterface;
use Rodas\Psr\Http\Message\UploadedFileFactoryInterface;
use Rodas\Psr\Http\Message\UploadedFileInterface;

use const UPLOAD_ERR_OK;

class UploadedFileFactory implements UploadedFileFactoryInterface {
    /**
     * {@inheritDoc}
     */
    #[Override]
    public function createUploadedFile(
        StreamInterface $stream,
        ?int $size = null,
        int $error = UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null
    ): UploadedFileInterface {
        if ($size === null) {
            $size = $stream->getSize();
        }

        return new UploadedFile($stream, $size, $error, $clientFilename, $clientMediaType);
    }
}
