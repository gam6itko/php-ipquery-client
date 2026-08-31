<?php

declare(strict_types=1);

namespace Gam6itko\IPQuery;

/**
 * @psalm-type TIPQueryIsp = array{
 *     asn: string,
 *     org: string,
 *     isp: string
 * }
 * @psalm-type TIPQueryLocation = array{
 *     country: string,
 *     country_code: string,
 *     city: string,
 *     state: string,
 *     zipcode: string,
 *     latitude: float,
 *     longitude: float,
 *     timezone: string,
 *     localtime: string
 * }
 * @psalm-type TIPQueryRisk = array{
 *     abuse_confidence_score: int,
 *     usage_type: string,
 *     is_tor: bool,
 *     total_reports: int,
 *     number_of_users_reported: int,
 *     last_reported_at: string
 * }
 * @psalm-type TIPQueryResult = array{
 *     ip: string,
 *     isp: TIPQueryIsp,
 *     location: TIPQueryLocation,
 *     risk: TIPQueryRisk
 * }
 */
interface LookupInterface
{
    /**
     * Resolves geo data for the given IP address.
     *
     * `location.country_code` is an ISO 3166-1 alpha-2 code in UPPER case
     * (as returned by the geo service), or an empty string when the country
     * could not be determined.
     *
     * @return TIPQueryResult
     *
     * @throws LookupException when the request fails or the response is malformed
     */
    public function lookup(string $ip): array;
}
