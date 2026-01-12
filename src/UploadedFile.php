<?php
/**
 * This file is part of the Rodas\Diactoros
 *
 * Based on Laminas\Diactoros\UploadedFile.php
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
use Rodas\Psr\Http\Message\StreamInterface;
use Rodas\Psr\Http\Message\UploadedFileInterface;

use function assert;
use function dirname;
use function fclose;
use function file_exists;
use function fopen;
use function fwrite;
use function is_dir;
use function is_resource;
use function is_string;
use function is_writable;
use function move_uploaded_file;
use function str_starts_with;
use function unlink;

use const PHP_SAPI;
use const UPLOAD_ERR_CANT_WRITE;
use const UPLOAD_ERR_EXTENSION;
use const UPLOAD_ERR_FORM_SIZE;
use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_NO_TMP_DIR;
use const UPLOAD_ERR_OK;
use const UPLOAD_ERR_PARTIAL;

class UploadedFile implements UploadedFileInterface {
    // TODO: Use Resources
    public const ERROR_MESSAGES = [
        UPLOAD_ERR_OK         => 'There is no error, the file uploaded with success',
        UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was '
                               . 'specified in the HTML form',
        UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
    ];

    /**
     * {@inheritdoc}
     *
     * @see http://php.net/manual/en/features.file-upload.errors.php
     *
     * @var int One of PHP's UPLOAD_ERR_XXX constants.
     */
    public private(set) int $error {
        get => $this->error;
        set => $this->error = $value;
    }

    private ?string $file = null;

    private bool $moved = false;

    public private(set) StreamInterface $stream {
        get {
            if ($this->error !== UPLOAD_ERR_OK) {
                throw Exception\UploadedFileErrorException::dueToStreamUploadError(
                    self::ERROR_MESSAGES[$this->error]
                );
            }

            if ($this->moved) {
                throw new Exception\UploadedFileAlreadyMovedException();
            }

            if (isset($this->stream)) {
                return $this->stream;
            }

            assert($this->file !== null, 'Always true condition for psalm type safety');
            $this->stream = new Stream($this->file);
            return $this->stream;
        }
        set => $this->stream = $value;
    }

    /**
     * {@inheritdoc}
     *
     * @var int|null The file size in bytes or null if unknown.
     */
    public private(set) ?int $size {
        get => $this->size;
        set => $this->size = $value;
    }

    /**
     * {@inheritdoc}
     *
     * @var string|null The filename sent by the client or null if none was provided.
     */
    public private(set) ?string $clientFilename = null {
        get => $this->clientFilename;
        set => $this->clientFilename = $value;
    }

    /**
     * {@inheritdoc}
     */
    public private(set) ?string $clientMediaType = null {
        get => $this->clientMediaType;
        set => $this->clientMediaType = $value;
    }

    /**
     * @param string|resource|StreamInterface $streamOrFile
     * @throws InvalidArgumentException
     */
    public function __construct(
        $streamOrFile,
        ?int $size,
        int $errorStatus,
        ?string $clientFilename = null,
        ?string $clientMediaType = null
    ) {
        $this->size = $size;
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;
        $this->error = $errorStatus;
        if ($errorStatus === UPLOAD_ERR_OK) {
            if (is_string($streamOrFile)) {
                $this->file = $streamOrFile;
            } elseif (is_resource($streamOrFile)) {
                $this->stream = new Stream($streamOrFile);
            } else {
                if (! $streamOrFile instanceof StreamInterface) {
                    throw new InvalidArgumentException('Invalid stream or file provided for UploadedFile');
                }
                $this->stream = $streamOrFile;
            }
        }

        if (0 > $errorStatus ||
            8 < $errorStatus) {
            throw new InvalidArgumentException(
                'Invalid error status for UploadedFile; must be an UPLOAD_ERR_* constant'
            );
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see http://php.net/is_uploaded_file
     * @see http://php.net/move_uploaded_file
     *
     * @param string $targetPath Path to which to move the uploaded file.
     * @throws Exception\UploadedFileErrorException If the upload was not successful.
     * @throws InvalidArgumentException If the $path specified is invalid.
     * @throws Exception\UploadedFileErrorException On any error during the
     *     move operation, or on the second or subsequent call to the method.
     */
    #[Override]
    public function moveTo(string $targetPath): void
    {
        if ($this->moved) {
            throw new Exception\UploadedFileAlreadyMovedException('Cannot move file; already moved!');
        }

        if ($this->error !== UPLOAD_ERR_OK) {
            throw Exception\UploadedFileErrorException::dueToStreamUploadError(
                self::ERROR_MESSAGES[$this->error]
            );
        }

        if (empty($targetPath)) {
            throw new InvalidArgumentException(
                'Invalid path provided for move operation; must be a non-empty string'
            );
        }

        $targetDirectory = dirname($targetPath);
        if (! is_dir($targetDirectory) || ! is_writable($targetDirectory)) {
            throw Exception\UploadedFileErrorException::dueToUnwritableTarget($targetDirectory);
        }

        $sapi = PHP_SAPI;
        if (empty($sapi)
            || str_starts_with($sapi, 'cli')
            || str_starts_with($sapi, 'phpdbg')
            || $this->file === null) {
            // Non-SAPI environment, or no filename present

            $this->writeFile($targetPath);

            if (isset($this->stream)) {
                $this->stream->close();
            }
            if (is_string($this->file) && file_exists($this->file)) {
                unlink($this->file);
            }
        } else {
            // SAPI environment, with file present
            if (false === move_uploaded_file($this->file, $targetPath)) {
                throw Exception\UploadedFileErrorException::forUnmovableFile();
            }
        }

        $this->moved = true;
    }

    /**
     * Write internal stream to given path
     */
    private function writeFile(string $path): void {
        $handle = fopen($path, 'wb+');
        if (false === $handle) {
            throw Exception\UploadedFileErrorException::dueToUnwritablePath();
        }

        $stream = $this->stream;
        $stream->rewind();
        while (! $stream->eof()) {
            fwrite($handle, $stream->read(4096));
        }

        fclose($handle);
    }
}
