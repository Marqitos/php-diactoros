<?php
/**
 * This file is part of the Rodas\Diactoros
 *
 * Based on Laminas\Diactoros\Exception\UploadedFileErrorException.php
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

namespace Rodas\Diactoros\Exception;

use RuntimeException;

use function sprintf;

class UploadedFileErrorException extends RuntimeException {
    public static function forUnmovableFile(): self {
        return new self('Error occurred while moving uploaded file');
    }

    public static function dueToStreamUploadError(string $error): self {
        return new self(sprintf(
            'Cannot retrieve stream due to upload error: %s',
            $error
        ));
    }

    public static function dueToUnwritablePath(): self {
        return new self('Unable to write to designated path');
    }

    public static function dueToUnwritableTarget(string $targetDirectory): self {
        return new self(sprintf(
            'The target directory `%s` does not exist or is not writable',
            $targetDirectory
        ));
    }
}
