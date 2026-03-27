# wtsergo/misc

Collection of utility helpers: cryptographic key management, performance profiling, PSR-7 ↔ AMPHP protocol adapters, and async sequencing primitives.

## Key Abstractions

### CryptKey

Secure OpenSSL key holder with validation and permission checking. Derived from league/oauth2-server.

```php
new CryptKey(string $keyPath, ?string $passPhrase = null, bool $keyPermissionsCheck = true)
```

- Accepts file paths (with or without `file://` prefix) or raw key material
- Validates RSA and EC key types via OpenSSL
- Enforces file permissions: requires 400, 440, 600, 640, or 660
- Methods: `getKeyContents()`, `getKeyPath()`, `getPassPhrase()`

### ProfilerFacade / ProfilerStat

Static profiling facade with nested timer tracking and memory monitoring. Originally from Magento.

```php
ProfilerFacade::setEnabled(true);
ProfilerFacade::start('request->db->query');
// ... work ...
ProfilerFacade::stop('request->db->query');
ProfilerFacade::getAllStat(); // returns formatted strings with ms/KB
```

**ProfilerStat** — core engine:
- `start(timerId, time, realMemory, emallocMemory)` / `stop(...)` — accumulates stats
- Tracks: execution time, count, average, real memory, emalloc memory
- Supports hierarchical timer names via `->` separator (e.g., `request->db->query`)
- `getFilteredTimerIds(thresholds, filterPattern)` — ordered timer list with regex filtering

**ProfilerFacade** — static wrapper:
- All operations are silent no-ops when disabled
- `getAllStat()` returns human-readable formatted messages
- Used in deployed scripts: `ProfilerFacade::setEnabled(false)`

### PsrServerAdapter

Bidirectional converter between AMPHP native HTTP and PSR-7/PSR-15 objects.

- `toPsrServerRequest(Request): PsrServerRequest` — AMPHP → PSR-7
- `fromPsrServerRequest(PsrServerRequest, Client): Request` — PSR-7 → AMPHP
- `toPsrServerResponse(Response): PsrResponse` — AMPHP → PSR-7
- `fromPsrServerResponse(PsrResponse, Request): Response` — PSR-7 → AMPHP
- Maps headers, cookies, query params, attributes, and body
- `withBody` flag to optionally exclude body from conversion
- Used by OAuth server to bridge League OAuth2 (PSR-7) with AMPHP HTTP

### SharedSequence

Synchronization primitive for ordered execution in Revolt async runtime.

```php
$seq = new SharedSequence();
// In fiber A:
$seq->await(5);  // suspends until position >= 5
// In fiber B:
$seq->resume(5); // advances to position 6, resumes waiters
```

- `await(position)` — suspends coroutine until position reached
- `resume(position)` — advances to `max(position, current) + 1`, resumes all waiters up to that position
- Position is strictly monotonic — cannot go backwards
- Uses `EventLoop::getSuspension()` from Revolt

## Gotchas

- **CryptKey auto-detection**: If raw key content happens to be a valid file path, it will try to read it as a file first.
- **ProfilerFacade silent when disabled**: All operations return empty arrays/null with no indication profiling is off.
- **PsrServerAdapter body buffering**: `toPsrServerRequest()` buffers the body, extracts form values, then re-sets body — inefficient for large bodies.
- **PsrServerAdapter TODOs**: Protocol version handling and parsed body reverse conversion are incomplete.
- **SharedSequence monotonic**: Once `resume()` advances, earlier positions can't be awaited. Design assumes strict sequential ordering.
