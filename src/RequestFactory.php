<?php
/**
 * This file is part of the Rodas\Diactoros
 *
 * Based on Laminas\Diactoros\RequestFactory.php
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
use Rodas\Psr\Http\Message\RequestFactoryInterface;
use Rodas\Psr\Http\Message\UriInterface;

class RequestFactory implements RequestFactoryInterface {
    /**
     * {@inheritDoc}
     */
    #[Override]
    public function createRequest(string $method, string|UriInterface $uri): Request {
        return new Request($uri, $method);
    }
}
