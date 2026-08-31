<?php

declare(strict_types=1);

namespace Gam6itko\IPQuery\Tests;

use Gam6itko\IPQuery\InvalidIpException;
use Gam6itko\IPQuery\LookupException;
use Gam6itko\IPQuery\LookupInterface;
use Gam6itko\IPQuery\RetryingIPQuery;
use Gam6itko\IPQuery\Tests\Fixture\SequenceLookup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @psalm-import-type TIPQueryResult from LookupInterface
 */
#[CoversClass(RetryingIPQuery::class)]
final class RetryingIPQueryTest extends TestCase
{
    /** @var list<int> */
    private array $slept = [];

    public function testReturnsFirstSuccessWithoutRetrying(): void
    {
        $result = self::ipQueryResult('RU');
        $inner = new SequenceLookup($result);

        self::assertSame($result, $this->makeSut($inner)->lookup('8.8.8.8'));
        self::assertSame(1, $inner->calls);
        self::assertSame([], $this->slept);
    }

    public function testRetriesTransientFailureThenSucceeds(): void
    {
        $result = self::ipQueryResult('DE');
        $inner = new SequenceLookup(
            new LookupException('boom', null, 503),
            new LookupException('boom', null, 500),
            $result,
        );

        self::assertSame($result, $this->makeSut($inner, maxAttempts: 3)->lookup('8.8.8.8'));
        self::assertSame(3, $inner->calls);
        // Exponential backoff before each retry: base (100 ms), then 2× (200 ms), in microseconds.
        self::assertSame([100_000, 200_000], $this->slept);
    }

    public function testRethrowsLastFailureWhenAttemptsExhausted(): void
    {
        $last = new LookupException('still down', null, 503);
        $inner = new SequenceLookup(
            new LookupException('down', null, 503),
            $last,
        );

        try {
            $this->makeSut($inner, maxAttempts: 2)->lookup('8.8.8.8');
            self::fail('LookupException was not thrown');
        } catch (LookupException $e) {
            self::assertSame($last, $e);
        }

        self::assertSame(2, $inner->calls);
        self::assertSame([100_000], $this->slept);
    }

    public function testDoesNotRetryInvalidIp(): void
    {
        $inner = new SequenceLookup(InvalidIpException::forIp('nope'));

        $this->expectException(InvalidIpException::class);

        try {
            $this->makeSut($inner, maxAttempts: 5)->lookup('nope');
        } finally {
            self::assertSame(1, $inner->calls);
            self::assertSame([], $this->slept);
        }
    }

    #[DataProvider('dataNonRetryableStatus')]
    public function testDoesNotRetryDeterministicHttpErrors(int $status): void
    {
        $inner = new SequenceLookup(new LookupException('client error', null, $status));

        try {
            $this->makeSut($inner, maxAttempts: 5)->lookup('8.8.8.8');
            self::fail('LookupException was not thrown');
        } catch (LookupException $e) {
            self::assertSame($status, $e->getStatusCode());
        }

        self::assertSame(1, $inner->calls);
        self::assertSame([], $this->slept);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function dataNonRetryableStatus(): iterable
    {
        yield '400' => [400];
        yield '404' => [404];
        yield '403' => [403];
    }

    #[DataProvider('dataRetryableFailure')]
    public function testRetriesEachTransientClass(LookupException $failure): void
    {
        $result = self::ipQueryResult('US');
        $inner = new SequenceLookup($failure, $result);

        self::assertSame($result, $this->makeSut($inner, maxAttempts: 2)->lookup('8.8.8.8'));
        self::assertSame(2, $inner->calls);
    }

    /**
     * @return iterable<string, array{LookupException}>
     */
    public static function dataRetryableFailure(): iterable
    {
        yield 'transport (null status)' => [new LookupException('transport', null, null)];
        yield '500'                     => [new LookupException('server', null, 500)];
        yield '503'                     => [new LookupException('unavailable', null, 503)];
        yield '429 rate limited'        => [new LookupException('slow down', null, 429)];
    }

    public function testMaxAttemptsOneDisablesRetrying(): void
    {
        $inner = new SequenceLookup(new LookupException('down', null, 503));

        $this->expectException(LookupException::class);

        try {
            $this->makeSut($inner, maxAttempts: 1)->lookup('8.8.8.8');
        } finally {
            self::assertSame(1, $inner->calls);
            self::assertSame([], $this->slept);
        }
    }

    public function testAppliesConstructorDefaults(): void
    {
        // No maxAttempts / baseDelayMs passed: exercise the constructor defaults
        // (3 attempts, 100 ms base backoff) rather than makeSut()'s explicit values.
        $inner = new SequenceLookup(
            new LookupException('down', null, 503),
            new LookupException('down', null, 503),
            new LookupException('still down', null, 503),
        );

        $sut = new RetryingIPQuery($inner, sleep: $this->recordSleep());

        $this->expectException(LookupException::class);

        try {
            $sut->lookup('8.8.8.8');
        } finally {
            // Default maxAttempts is 3 (a 4th call would exhaust the sequence).
            self::assertSame(3, $inner->calls);
            // Default baseDelayMs is 100: backoff of 100 ms then 200 ms, in microseconds.
            self::assertSame([100_000, 200_000], $this->slept);
        }
    }

    public function testCustomRetryablePolicyIsHonored(): void
    {
        $result = self::ipQueryResult('FR');
        // Retry a 404, which the default policy would not.
        $inner = new SequenceLookup(new LookupException('not found', null, 404), $result);

        $retryable = static fn (LookupException $e): bool => 404 === $e->getStatusCode();

        $sut = new RetryingIPQuery($inner, maxAttempts: 2, baseDelayMs: 0, retryable: $retryable, sleep: $this->recordSleep());

        self::assertSame($result, $sut->lookup('8.8.8.8'));
        self::assertSame(2, $inner->calls);
    }

    #[DataProvider('dataInvalidConfig')]
    public function testRejectsInvalidConfiguration(int $maxAttempts, int $baseDelayMs): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RetryingIPQuery(new SequenceLookup(), maxAttempts: $maxAttempts, baseDelayMs: $baseDelayMs);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function dataInvalidConfig(): iterable
    {
        yield 'zero attempts'     => [0, 100];
        yield 'negative attempts' => [-1, 100];
        yield 'negative delay'    => [3, -1];
    }

    private function makeSut(LookupInterface $inner, int $maxAttempts = 3): RetryingIPQuery
    {
        return new RetryingIPQuery($inner, maxAttempts: $maxAttempts, baseDelayMs: 100, sleep: $this->recordSleep());
    }

    /**
     * @return \Closure(int): void
     */
    private function recordSleep(): \Closure
    {
        return function (int $micros): void {
            $this->slept[] = $micros;
        };
    }

    /**
     * @return TIPQueryResult
     */
    private static function ipQueryResult(string $countryCode): array
    {
        return [
            'ip'       => '8.8.8.8',
            'isp'      => ['asn' => '', 'org' => '', 'isp' => ''],
            'location' => [
                'country'      => '',
                'country_code' => $countryCode,
                'city'         => '',
                'state'        => '',
                'zipcode'      => '',
                'latitude'     => 0.0,
                'longitude'    => 0.0,
                'timezone'     => '',
                'localtime'    => '',
            ],
            'risk' => [
                'abuse_confidence_score'   => 0,
                'usage_type'               => '',
                'is_tor'                   => false,
                'total_reports'            => 0,
                'number_of_users_reported' => 0,
                'last_reported_at'         => '',
            ],
        ];
    }
}
