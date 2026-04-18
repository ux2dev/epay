# CLAUDE.md

Context and conventions for AI assistants and contributors working on this SDK. Keep this short and factual — the full user-facing docs live in `README.md`.

## What this is

Framework-agnostic PHP SDK for the Bulgarian ePay.bg / EasyPay payment gateway. Covers four APIs, each under its own namespace:

- `Ux2Dev\Epay\Web` — standard web payments (customer is redirected to ePay to pay)
- `Ux2Dev\Epay\Billing` — recurring obligations (ePay calls **us**; opposite direction)
- `Ux2Dev\Epay\OneTouch` — tokenised card-on-file
- `Ux2Dev\Epay\EasyPay` — cash-desk payments with IDN codes

Shared primitives live in `Signing`, `Config`, `Enum`, `Exception`, `IdnGenerator`, `KeyGenerator`.

The optional `Laravel/` subtree is a service-provider wrapper. The core SDK must remain framework-agnostic — don't leak Laravel types or helpers into the non-Laravel folders.

## How things work (high level)

- **One merchant config, many clients.** `MerchantConfig` holds secret / merchant id / environment. Each API has its own client (`WebClient`, `BillingHandler`, `OneTouchClient`, `EasyPayClient`) constructed from that config.
- **Signing is centralised.** `Signing/HmacSigner` (HMAC-SHA1) and `Signing/RsaSigner` are the only places that touch crypto. Anything that needs to sign goes through a signer; anything that verifies does the same. Do not inline `hash_hmac` calls elsewhere.
- **Requests are DTOs.** Incoming data is parsed into readonly DTOs (`InitRequest`, `ConfirmRequest`, notification items). Responses are built via static factory methods (`InitResponse::success(...)`, `ConfirmResponse::duplicate()`).
- **Errors throw typed exceptions.** All SDK exceptions extend `Ux2Dev\Epay\Exception\EpayException`. `InvalidResponseException` redacts `CHECKSUM`, `ENCODED`, `SIGNATURE`, `TOKEN` from its context data.

## Non-obvious protocol details (easy to get wrong)

These cost real hours if missed. When touching the relevant code paths, keep them in mind.

### Billing CHECKSUM has a trailing newline

`BillingHandler::buildChecksumData()` produces `KEY1VALUE1\nKEY2VALUE2\n...\n` — **including a trailing `\n` after the last pair**. Real ePay requests sign with it; if you omit it, every live request returns STATUS=96. There is a regression test pinned against a real ePay CHECKSUM in `tests/Billing/BillingHandlerTest.php`.

### Billing IDN must be digits-only

No letters, no dashes, no separators. Parent / child (invoice) identifiers must both be pure digits — e.g. parent `2000001`, sub-invoice `2000001001`, not `2000001-F001`. `IdnGenerator::validate()` enforces this. Any value that might end up in an IDN has to pass the validator before going out.

### Notification param casing

ePay sends WEB notification fields in **lowercase**, but `WebClient::handleNotification()` and every signed-data helper expect **uppercase** keys. The Laravel `WebNotificationController` does `array_change_key_case($request->post(), CASE_UPPER)` before handing off. Any new entry point that consumes `$_POST` from ePay must do the same.

### LONGDESC has its own escape sequences

The `LONGDESC` field in Billing responses uses `\n`, `\t`, and `$` as literal escape sequences (not real control bytes). Use `Billing/Formatter/LongDescFormatter::encode/decode`. Max 110 characters per line.

### One Touch auth vs. noreg callbacks share one URL

ePay POSTs both the authorization callback and the no-registration payment redirect to the same URL. They're disambiguated by the presence of the `id` query param (noreg has it; auth doesn't). `Laravel/Http/Controllers/OneTouchCallbackController` handles the split — mirror that logic if you expose the callback outside Laravel.

### Authorization callback does NOT auto-exchange the key

Exchanging the OAuth-style auth code for a token requires the app-stored `KEY` that was used to build the auth URL. The SDK deliberately does not do this automatically — the app owns the key/session. The callback fires an event; the application listener does the exchange.

## Laravel integration

- **Routes are opt-in.** `config('epay.routes.enabled')` defaults to `false`. When enabled, `src/Laravel/routes.php` registers:
  - `POST {prefix}/notify` → `WebNotificationController`
  - `GET  {prefix}/billing/init` → `BillingController@init`
  - `GET  {prefix}/billing/confirm` → `BillingController@confirm`
  - `GET  {prefix}/callback` → `OneTouchCallbackController`
- **Billing resolvers are mandatory.** The controller calls closures registered via `Epay::billingInitUsing(...)` / `Epay::billingConfirmUsing(...)`. Without them the controller throws `LogicException` — intentional; fail loud rather than silently returning empty JSON.
- **Events, not inheritance.** Every controller dispatches a typed event (`PaymentReceived`, `BillingObligationChecked`, etc.). Apps wire listeners; the SDK never touches their domain.
- **Multi-tenancy.** `EpayManager::merchant($name)` returns a cloned manager bound to a different config. The current merchant is stored per-clone, so listeners can read `$event->merchant` to route by tenant.

## Testing

- Pest PHP (`vendor/bin/pest`). 260+ tests cover parsers, signers, DTOs, response builders, and Laravel controllers.
- `tests/TestCase.php` is the Orchestra Testbench base for Laravel tests.
- When adding a parser or signer, write both a happy-path test and a "wrong CHECKSUM / wrong key / missing field" negative test. `InvalidResponseException` is the expected outcome for signed-data failures.
- Keep regression tests for anything that took real debugging to discover — like the Billing trailing-newline test. Pinning a known-good CHECKSUM from a real request is worth more than any amount of synthetic coverage.

## Code conventions

- `declare(strict_types=1);` in every file.
- `final readonly` DTOs by default; rely on constructor promotion.
- Static factories for responses (`InitResponse::success(...)`). Do not expose public constructors for response objects.
- Namespaced per subsystem under `Ux2Dev\Epay\`. Tests mirror the namespace structure under `tests/`.
- No inline crypto, no inline HTTP. Go through the injected signer and the Guzzle client respectively.
- PHP 8.2+. Use enums, readonly, named args freely.

## Reference

- A complete, working end-to-end demo (plain PHP, no framework) lives in a sibling `epay-project` repo. It exercises every public API path — useful as a live reference when the protocol spec is ambiguous.
- ePay's own merchant portal: `mrcs.easypay.bg`. Billing integrations must be registered there.
- Test gateway: `demo.epay.bg`.
