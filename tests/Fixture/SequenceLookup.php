<?php

declare(strict_types=1);

namespace Gam6itko\IPQuery\Tests\Fixture;

use Gam6itko\IPQuery\LookupException;
use Gam6itko\IPQuery\LookupInterface;

/**
 * Test double that returns (or throws) a predefined sequence of outcomes,
 * one per lookup() call, and records how many times it was called.
 *
 * @psalm-import-type TIPQueryResult from LookupInterface
 */
final class SequenceLookup implements LookupInterface
{
    public int $calls = 0;

    /** @var list<TIPQueryResult|LookupException> */
    private array $outcomes;

    /**
     * @param TIPQueryResult|LookupException ...$outcomes
     */
    public function __construct(array|LookupException ...$outcomes)
    {
        $this->outcomes = \array_values($outcomes);
    }

    /**
     * @return TIPQueryResult
     *
     * @throws LookupException
     */
    public function lookup(string $ip): array
    {
        $outcome = $this->outcomes[$this->calls]
            ?? throw new \LogicException('SequenceLookup ran out of configured outcomes');
        ++$this->calls;

        if ($outcome instanceof LookupException) {
            throw $outcome;
        }

        return $outcome;
    }
}
