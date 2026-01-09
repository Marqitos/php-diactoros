<?php
/**
 * This file is part of the Rodas\Diactoros
 *
 * Based on Laminas\Diactoros\Response\InjectContentTypeTrait.php
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

namespace Rodas\Diactoros\Response;

use function array_keys;
use function array_reduce;
use function strtolower;

trait InjectContentTypeTrait {
    /**
     * Inject the provided Content-Type, if none is already present.
     *
     * @param array<non-empty-string, string|string[]> $headers
     * @return array<non-empty-string, string|string[]> Headers with injected Content-Type
     */
    private function injectContentType(string $contentType, array $headers): array {
        $hasContentType = array_reduce(
            array_keys($headers),
            static fn(bool $carry, string $item): bool => $carry ?: strtolower($item) === 'content-type',
            false
        );

        if (! $hasContentType) {
            $headers['content-type'] = [$contentType];
        }

        return $headers;
    }
}
