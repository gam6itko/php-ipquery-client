<?php

declare(strict_types=1);

namespace Gam6itko\IPQuery;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriFactoryInterface;

/**
 * PSR-17 request factory that prepends the geo-service base URI to relative paths.
 *
 * IPQueryClient passes only a relative path (e.g. `/lookup/8.8.8.8`); this decorator
 * turns it into an absolute request against $baseUri, delegating the actual message
 * creation to the injected PSR-17 factories. Any PSR-7 implementation works.
 *
 * @see IPQueryClient
 */
final class IPQueryRequestFactory implements RequestFactoryInterface
{
    /**
     * @param string $baseUri geo-service base address, e.g. `http://localhost:8080`
     */
    public function __construct(
        private readonly RequestFactoryInterface $requestFactory,
        private readonly UriFactoryInterface $uriFactory,
        private readonly string $baseUri = 'http://localhost:8080',
    ) {
    }

    public function createRequest(string $method, $uri): RequestInterface
    {
        if (\is_string($uri)) {
            $uri = $this->uriFactory->createUri($this->baseUri)->withPath($uri);
        }

        return $this->requestFactory->createRequest($method, $uri);
    }
}
