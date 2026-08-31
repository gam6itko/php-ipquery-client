<?php

declare(strict_types=1);

namespace Gam6itko\IPQuery;

/**
 * Test double for the client contract.
 *
 * @see LookupInterface
 *
 * @psalm-import-type TIPQueryResult from LookupInterface
 */
final readonly class IPQueryStub implements LookupInterface
{
    /**
     * @param TIPQueryResult|\Throwable $result the value lookup() will return,
     *                                          or the exception it will throw
     */
    public function __construct(
        private array|\Throwable $result,
    ) {
    }

    /**
     * @return TIPQueryResult
     *
     * @throws \Throwable
     */
    #[\Override]
    public function lookup(string $ip): array
    {
        if ($this->result instanceof \Throwable) {
            throw $this->result;
        }

        return $this->result;
    }
}
