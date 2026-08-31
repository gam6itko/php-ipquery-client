<?php

declare(strict_types=1);

namespace Gam6itko\IPQuery;

/**
 * Retries transient lookup failures with exponential backoff.
 *
 * By default a failure is retried when it looks transient — a transport error
 * (no HTTP status), a 5xx status, or 429 Too Many Requests. Deterministic
 * failures are never retried: {@see InvalidIpException} and other 4xx statuses
 * fail fast. The policy can be replaced via the $retryable callback.
 *
 * As a {@see LookupInterface} decorator it composes with the others, e.g.
 * `new CachedIPQuery(new RetryingIPQuery($client), $cache)` — retry the live
 * call, then cache the successful result.
 *
 * @psalm-import-type TIPQueryResult from LookupInterface
 */
final readonly class RetryingIPQuery implements LookupInterface
{
    /** @var \Closure(LookupException, int): bool */
    private \Closure $retryable;

    /** @var \Closure(int): void */
    private \Closure $sleep;

    /**
     * @param int                                         $maxAttempts total number of attempts, including the first (1 disables retrying); must be >= 1
     * @param int                                         $baseDelayMs base backoff in milliseconds; doubles on each retry; must be >= 0
     * @param (\Closure(LookupException, int): bool)|null $retryable   decides whether a failed attempt
     *                                                                 (exception, 1-based attempt number)
     *                                                                 should be retried; null uses the default policy
     * @param (\Closure(int): void)|null                  $sleep       waits the given number of microseconds; null uses \usleep().
     *                                                                 Injectable so tests need not sleep for real
     */
    public function __construct(
        private LookupInterface $inner,
        private int $maxAttempts = 3,
        private int $baseDelayMs = 100,
        ?\Closure $retryable = null,
        ?\Closure $sleep = null,
    ) {
        if ($maxAttempts < 1) {
            throw new \InvalidArgumentException(\sprintf('maxAttempts must be >= 1, got %d', $maxAttempts));
        }
        if ($baseDelayMs < 0) {
            throw new \InvalidArgumentException(\sprintf('baseDelayMs must be >= 0, got %d', $baseDelayMs));
        }

        $this->retryable = $retryable ?? self::defaultRetryable(...);
        $this->sleep = $sleep ?? static function (int $micros): void {
            \usleep($micros);
        };
    }

    /**
     * @return TIPQueryResult
     *
     * @throws LookupException the last failure once attempts are exhausted or the failure is not retryable
     */
    #[\Override]
    public function lookup(string $ip): array
    {
        $attempt = 1;

        while (true) {
            try {
                return $this->inner->lookup($ip);
            } catch (LookupException $e) {
                if ($attempt >= $this->maxAttempts || !($this->retryable)($e, $attempt)) {
                    throw $e;
                }

                ($this->sleep)($this->delayMicros($attempt));
                ++$attempt;
            }
        }
    }

    /**
     * Backoff before the retry that follows the given (failed) attempt: base, 2×base, 4×base, ….
     */
    private function delayMicros(int $attempt): int
    {
        return $this->baseDelayMs * 1000 * (2 ** ($attempt - 1));
    }

    private static function defaultRetryable(LookupException $e, int $attempt): bool
    {
        // A bad IP is the caller's mistake — retrying it is pointless.
        if ($e instanceof InvalidIpException) {
            return false;
        }

        $status = $e->getStatusCode();

        // null: transport/parsing failure (often transient). 5xx: server-side. 429: rate limited.
        return null === $status || $status >= 500 || 429 === $status;
    }
}
