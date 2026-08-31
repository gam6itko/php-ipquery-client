<?php

declare(strict_types=1);

namespace Gam6itko\IPQuery\Tests;

use Gam6itko\IPQuery\IPQueryRequestFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(IPQueryRequestFactory::class)]
final class IPQueryRequestFactoryTest extends TestCase
{
    #[DataProvider('dataBaseUri')]
    public function testBaseUriIsPrependedToPath(string $baseUri, string $path, string $expectedUri): void
    {
        $psr17 = new Psr17Factory();
        $factory = new IPQueryRequestFactory($psr17, $psr17, $baseUri);

        $request = $factory->createRequest('GET', $path);

        self::assertSame('GET', $request->getMethod());
        self::assertSame($expectedUri, (string) $request->getUri());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function dataBaseUri(): iterable
    {
        yield 'host with port' => [
            'http://ip-query:8080',
            '/lookup/1.2.3.4',
            'http://ip-query:8080/lookup/1.2.3.4',
        ];

        yield 'host without port' => [
            'https://ip-query.example.com',
            '/lookup/1.2.3.4',
            'https://ip-query.example.com/lookup/1.2.3.4',
        ];

        // A trailing slash in the base address must not leak into the path.
        yield 'base uri with trailing slash' => [
            'http://ip-query:8080/',
            '/lookup/1.2.3.4',
            'http://ip-query:8080/lookup/1.2.3.4',
        ];
    }
}
