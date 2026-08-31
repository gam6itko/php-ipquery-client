# php-ipquery-client

[![CI](https://github.com/gam6itko/php-ipquery-client/actions/workflows/ci.yml/badge.svg)](https://github.com/gam6itko/php-ipquery-client/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/gam6itko/ipquery-client/v)](https://packagist.org/packages/gam6itko/ipquery-client)
[![License](https://poser.pugx.org/gam6itko/ipquery-client/license)](https://packagist.org/packages/gam6itko/ipquery-client)

Framework-agnostic PHP client for a **self-hosted** IPQuery geo service: resolve country,
ISP and risk data by IP address. Built on PSR-18 (HTTP client) and PSR-17 (HTTP factories),
so it works with any PSR-7 implementation.

> [!IMPORTANT]
> This client targets the **self-hosted** [`akyriako/ipquery`](https://github.com/akyriako/ipquery)
> server (endpoint `GET /lookup/{ip}`). It is **NOT** compatible with the public SaaS
> [ipquery.io](https://ipquery.io/) — that service has a different API (endpoints and response
> shape). For `ipquery.io` use one of these instead:
> - [`esi/ipquery-php`](https://github.com/ericsizemore/ipquery-php)
> - [`guibranco/ipquery-php`](https://github.com/guibranco/ipquery-php)

## Installation

```bash
composer require gam6itko/ipquery-client

# plus any PSR-18 client and PSR-7/17 implementation, e.g.:
composer require symfony/http-client nyholm/psr7
```

## Usage

```php
use Gam6itko\IPQuery\IPQueryClient;
use Gam6itko\IPQuery\IPQueryRequestFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\HttpClient\Psr18Client;

$psr17 = new Psr17Factory();

$client = new IPQueryClient(
    httpClient: new Psr18Client(),
    // IPQueryRequestFactory prepends the geo-service base URI to the relative path.
    requestFactory: new IPQueryRequestFactory($psr17, $psr17, 'http://localhost:8080'),
);

$result = $client->lookup('8.8.8.8'); // throws Gam6itko\IPQuery\LookupException on failure
echo $result['location']['country_code']; // ISO 3166-1 alpha-2 in UPPER case, e.g. "US"

// Caller's own IP as seen by the geo service:
$client->own();   // full metadata (GET /own/all), same shape as lookup()
$client->ownIp(); // just the IP string (GET /own)
```

### Endpoints

| Method                | Server endpoint   | Returns                                     |
|-----------------------|-------------------|---------------------------------------------|
| `lookup(string $ip)`  | `GET /lookup/{ip}`| `TIPQueryResult` for the given IP           |
| `own()`               | `GET /own/all`    | `TIPQueryResult` for the caller's own IP    |
| `ownIp()`             | `GET /own`        | `non-empty-string` — the caller's own IP    |

> `own()` / `ownIp()` live on `IPQueryClient` only; `LookupInterface` (the cacheable,
> mockable contract used via `CachedIPQuery` / `IPQueryStub`) exposes just `lookup()`.

### Caching

`CachedIPQuery` decorates any `IPQueryInterface` with a PSR-16 cache (the IP-to-country
mapping changes rarely; failures are never cached):

```php
use Gam6itko\IPQuery\CachedIPQuery;

$cached = new CachedIPQuery($client, $psr16Cache);
$cached->lookup('8.8.8.8');
```

### Testing

Use `IPQueryStub` as a test double — it returns a canned result or throws a given exception:

```php
use Gam6itko\IPQuery\IPQueryStub;
use Gam6itko\IPQuery\LookupException;

new IPQueryStub($lookupResult);
new IPQueryStub(new LookupException('geo is down'));
```

## Response contract

`lookup()` returns the decoded `/lookup/{ip}` payload (psalm type `TIPQueryResult`):

| Key        | Shape                                                                             |
|------------|-----------------------------------------------------------------------------------|
| `ip`       | `string`                                                                          |
| `isp`      | `{asn, org, isp}`                                                                 |
| `location` | `{country, country_code, city, state, zipcode, latitude, longitude, timezone, localtime}` |
| `risk`     | `{abuse_confidence_score, usage_type, is_tor, total_reports, number_of_users_reported, last_reported_at}` |

## Development

```bash
composer tests   # phpunit
composer psalm   # static analysis (strict, full type coverage)
composer csfix   # php-cs-fixer
```

## License

MIT — see [LICENSE](LICENSE).
