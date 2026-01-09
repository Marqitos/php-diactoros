<?php
/**
 * This file is part of the Rodas\Diactoros
 *
 * Based on Laminas\Diactoros\ConfigProvider.php
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

use Rodas\Psr\Http\Message\RequestFactoryInterface;
use Rodas\Psr\Http\Message\ResponseFactoryInterface;
use Rodas\Psr\Http\Message\ServerRequestFactoryInterface;
use Rodas\Psr\Http\Message\StreamFactoryInterface;
use Rodas\Psr\Http\Message\UploadedFileFactoryInterface;
use Rodas\Psr\Http\Message\UriFactoryInterface;

class ConfigProvider {
    public const CONFIG_KEY                  = 'rodas-diactoros';
    public const X_FORWARDED                 = 'x-forwarded-request-filter';
    public const X_FORWARDED_TRUSTED_PROXIES = 'trusted-proxies';
    public const X_FORWARDED_TRUSTED_HEADERS = 'trusted-headers';

    /**
     * Retrieve configuration for rodas-diactoros.
     */
    public function __invoke(): array {
        return [
            'dependencies'   => $this->getDependencies(),
            self::CONFIG_KEY => $this->getComponentConfig(),
        ];
    }

    /**
     * Returns the container dependencies.
     * Maps factory interfaces to factories.
     */
    public function getDependencies(): array {
        // @codingStandardsIgnoreStart
        return [
            'invokables' => [
                RequestFactoryInterface::class => RequestFactory::class,
                ResponseFactoryInterface::class => ResponseFactory::class,
                StreamFactoryInterface::class => StreamFactory::class,
                ServerRequestFactoryInterface::class => ServerRequestFactory::class,
                UploadedFileFactoryInterface::class => UploadedFileFactory::class,
                UriFactoryInterface::class => UriFactory::class
            ],
        ];
        // @codingStandardsIgnoreEnd
    }

    public function getComponentConfig(): array {
        return [
            self::X_FORWARDED => [
                self::X_FORWARDED_TRUSTED_PROXIES => '',
                self::X_FORWARDED_TRUSTED_HEADERS => [],
            ],
        ];
    }
}
