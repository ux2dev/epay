# ePay.bg PHP SDK -- Design Specification

## Overview

Open-source, framework-agnostic PHP SDK for ePay.bg / EasyPay payment gateway. Provides full coverage of all three ePay APIs: WEB, One Touch, and Billing. Follows the same architecture patterns as the Borica SDK (`ux2dev/borica`).

- **Package:** `ux2dev/epay-easypay`
- **Namespace:** `Ux2Dev\Epay`
- **PHP:** >= 8.2
- **Framework:** Core is framework-agnostic. Laravel integration layer on top.
- **License:** MIT (same as Borica)

## Architecture

Three independent clients share common infrastructure (Config, Signing, Enums, Exceptions):

- `WebClient` -- browser redirect / form POST based payments
- `OneTouchClient` -- REST API with token-based payments (PSR-18)
- `BillingHandler` -- callback-based periodic payments (ePay calls merchant server)

This separation reflects the fundamentally different paradigms of each API. Each client is focused, independently testable, and understandable on its own.

## Directory Structure

```
src/
├── Config/
│   └── MerchantConfig.php
├── Enum/
│   ├── Currency.php                 # BGN, EUR, USD (default: EUR)
│   ├── Environment.php              # Development (demo.epay.bg), Production (www.epay.bg)
│   ├── PaymentStatus.php            # Paid, Denied, Expired
│   ├── SigningMethod.php            # HmacSha1, Rsa
│   └── TransactionType.php          # Payment, CreditPayDirect, BankTransfer, EasyPay, PreAuth
├── Exception/
│   ├── EpayException.php            # Base exception
│   ├── ConfigurationException.php
│   ├── SigningException.php
│   └── InvalidResponseException.php
├── Signing/
│   ├── SignerInterface.php
│   ├── HmacSigner.php               # HMAC-SHA1 with secret word
│   └── RsaSigner.php                # RSA digital signature
├── KeyGenerator/
│   └── RsaKeyGenerator.php
├── IdnGenerator/
│   └── IdnGenerator.php
├── Web/
│   ├── WebClient.php
│   ├── Request/
│   │   ├── RequestInterface.php
│   │   ├── PaymentRequest.php        # PAGE=paylogin, ENCODED+CHECKSUM
│   │   ├── DirectPaymentRequest.php  # PAGE=credit_paydirect
│   │   ├── BankTransferRequest.php   # MERCHANT, IBAN, BIC
│   │   └── SimplePaymentRequest.php  # Simplified variant (MIN, INVOICE, TOTAL)
│   ├── Response/
│   │   ├── PaymentResponse.php
│   │   └── ResponseParser.php
│   └── Notification/
│       ├── NotificationHandler.php
│       └── NotificationItem.php
├── OneTouch/
│   ├── OneTouchClient.php
│   ├── Request/
│   │   ├── TokenRequest.php
│   │   ├── PaymentInitRequest.php
│   │   ├── PaymentCheckRequest.php
│   │   ├── PaymentSendRequest.php
│   │   ├── PaymentStatusRequest.php
│   │   ├── NoRegPaymentRequest.php
│   │   └── NoRegPaymentStatusRequest.php
│   └── Response/
│       ├── TokenResponse.php
│       ├── CodeResponse.php
│       ├── UserInfoResponse.php
│       ├── PaymentInstrument.php
│       ├── PaidWith.php
│       ├── PaymentResponse.php
│       └── NoRegPaymentResponse.php
├── Billing/
│   ├── BillingHandler.php
│   ├── Request/
│   │   ├── InitRequest.php
│   │   └── ConfirmRequest.php
│   ├── Response/
│   │   ├── InitResponse.php
│   │   ├── ConfirmResponse.php
│   │   └── Invoice.php
│   ├── Enum/
│   │   ├── BillingStatus.php         # 00, 13, 14, 62, 80, 93, 94, 96
│   │   ├── BillingRequestType.php    # CHECK, BILLING, DEPOSIT
│   │   └── BillingPaymentType.php    # BILLING, PARTIAL, DEPOSIT
│   ├── FileExchange/
│   │   └── ObligationFileGenerator.php
│   └── Formatter/
│       └── LongDescFormatter.php     # \n=newline, \t=8 spaces, \$=8 dashes, max 110 chars/line
└── Laravel/
    ├── EpayServiceProvider.php
    ├── EpayFacade.php
    ├── EpayManager.php               # Multi-tenancy merchant resolution
    ├── Http/
    │   ├── Controllers/
    │   │   ├── WebNotificationController.php
    │   │   └── BillingController.php
    │   └── Middleware/
    │       └── VerifyEpayChecksum.php
    ├── Events/
    │   ├── PaymentReceived.php
    │   ├── PaymentDenied.php
    │   ├── PaymentExpired.php
    │   ├── BillingObligationChecked.php
    │   └── BillingPaymentConfirmed.php
    ├── Commands/
    │   ├── GenerateKeyCommand.php
    │   └── GenerateObligationsCommand.php
    └── config/
        └── epay.php
```

## MerchantConfig

Immutable configuration object. Validates all inputs at construction time. Prevents serialization (`__serialize()` and `__unserialize()` both throw). Redacts sensitive data in `__debugInfo__()`.

```php
readonly class MerchantConfig
{
    public function __construct(
        public string $merchantId,           // KIN -- required
        string $secret,                      // HMAC secret word -- required
        public Environment $environment,     // Development or Production
        public Currency $currency = Currency::EUR,
        public SigningMethod $signingMethod = SigningMethod::HmacSha1,
        ?string $privateKey = null,          // PEM or file path -- required for RSA
        ?string $privateKeyPassphrase = null,
    );
}
```

Validation rules:
- `merchantId`: non-empty string
- `secret`: non-empty string, stored privately
- `privateKey`: if provided, must load via `openssl_pkey_get_private()`
- `privateKeyPassphrase`: only relevant when `privateKey` is set
- When `signingMethod` is `Rsa`, `privateKey` is required

## Signing

### SignerInterface

```php
interface SignerInterface
{
    public function sign(string $data): string;
    public function verify(string $data, string $signature): bool;
}
```

### HmacSigner

- `sign()`: returns `hmac_sha1_hex($data, $secret)`
- `verify()`: uses `hash_equals()` for constant-time comparison
- Used for CHECKSUM parameter in WEB API and Billing API

### RsaSigner

- `sign()`: RSA signature with merchant private key
- `verify()`: RSA verification with merchant public key
- Used for optional SIGNATURE parameter in WEB API

When `signingMethod` is `Rsa`, both CHECKSUM (HMAC) and SIGNATURE (RSA) are generated. HMAC is always present; RSA is additive.

## WEB API -- WebClient

### Request Types

**PaymentRequest** (PAGE=paylogin):
- Encodes data fields (MIN, INVOICE, AMOUNT, EXP_TIME, DESCR, CURRENCY, ENCODING, DISCOUNT) into a newline-separated string
- Base64 encodes (RFC 3548, EOL='')
- Signs ENCODED with HMAC-SHA1 -> CHECKSUM
- Optionally signs with RSA -> SIGNATURE
- `toArray()` returns hidden form fields: PAGE, ENCODED, CHECKSUM, SIGNATURE, URL_OK, URL_CANCEL
- `getGatewayUrl()` returns environment-specific URL
- Note: LANG is NOT supported for paylogin; only for credit_paydirect

**DirectPaymentRequest** (PAGE=credit_paydirect):
- Same encoding as PaymentRequest
- Adds LANG parameter (bg/en) -- only this request type supports LANG

**BankTransferRequest**:
- Direct fields, no ENCODED/CHECKSUM: MERCHANT, IBAN, BIC, TOTAL, STATEMENT, PSTATEMENT
- Validation: IBAN format, BIC format, TOTAL > 0.01, STATEMENT alphanumeric+punctuation, PSTATEMENT 6 digits

**SimplePaymentRequest** (simplified variant):
- Direct fields without encoding: MIN, INVOICE, TOTAL, DESCR, ENCODING
- Note: TOTAL and AMOUNT are equivalent (ePay accepts both)
- For merchants that don't need the full ENCODED flow

### Data Fields in ENCODED Payload

| Field | Required | Format | Description |
|-------|----------|--------|-------------|
| MIN | yes (or EMAIL) | int | Merchant KIN |
| EMAIL | yes (or MIN) | email | Merchant email |
| INVOICE | yes | string | Invoice number |
| AMOUNT | yes | decimal | Amount > 0.01 (e.g. 22.80) |
| EXP_TIME | yes | DD.MM.YYYY[ HH:mm[:ss]] | Expiration date/time |
| CURRENCY | no | BGN/EUR/USD | Default: BGN per ePay; SDK defaults to EUR in MerchantConfig |
| DESCR | no | string (max 100) | Description |
| ENCODING | no | string | Only "utf-8" accepted |
| DISCOUNT | no | cardbin,cardbin:amount | Card BIN discount rules (multiple lines allowed) |

### NotificationHandler

Processes callbacks from ePay after payment:

```php
$result = $web->handleNotification($postData);
```

1. Extracts ENCODED and CHECKSUM from POST data
2. Verifies CHECKSUM with `hash_equals()` -- throws `InvalidResponseException` on mismatch
3. Decodes ENCODED from base64
4. Parses lines: `INVOICE=X:STATUS=PAID:PAY_TIME=...:STAN=...:BCODE=...`
5. Returns `NotificationResult` with collection of `NotificationItem`

Each `NotificationItem` contains:
- `invoice`: string
- `status`: PaymentStatus (Paid/Denied/Expired)
- `payTime`: ?DateTimeImmutable (only when Paid)
- `stan`: ?string (only when Paid)
- `bcode`: ?string (only when Paid)
- `amount`: ?string (only when discount applied)
- `bin`: ?string (only when discount applied)

Merchant acknowledges each item:
- `$item->acknowledge()` -- STATUS=OK (stop retrying)
- `$item->reject()` -- STATUS=ERR (retry later)
- `$item->notFound()` -- STATUS=NO (stop retrying, invoice unknown)

`$result->toHttpResponse()` generates the plain text response body.

### Retry Schedule (ePay retries on ERR or no response)

1. 5 attempts < 1 minute
2. 4 attempts every 15 minutes
3. 5 attempts every 1 hour
4. 6 attempts every 3 hours
5. 4 attempts every 6 hours
6. 1 attempt daily

Total: up to 30 days. Stops on OK or NO.

## One Touch API -- OneTouchClient

REST client for tokenized payments. Requires PSR-18 `ClientInterface` and PSR-17 `RequestFactoryInterface`.

### Token Acquisition (three steps)

```php
$ot = new OneTouchClient($config, $httpClient, $requestFactory);

// Step 1: Generate authorization URL for user redirect
$authUrl = $ot->getAuthorizationUrl(
    deviceId: 'user@example.com',
    key: $uniqueKey,
    userType: null,           // 1=ePay users only, 2=cards only
    deviceName: null,         // optional device info
    brand: null,
    os: null,
    model: null,
    osVersion: null,
    phone: null,
);

// Step 2: Poll for authorization code
$code = $ot->getCode(deviceId: 'user@example.com', key: $uniqueKey);
// Returns CodeResponse with status and code
// Poll every 20-30 seconds, up to 30 minutes

// Step 3: Exchange code for token
$token = $ot->getToken(deviceId: 'user@example.com', code: $code->code);
// Returns TokenResponse: TOKEN, EXPIRES, KIN, USERNAME, REALNAME
```

### Token Management

```php
$ot->invalidateToken(deviceId: $deviceId, token: $token->token);
```

### User Information

```php
$userInfo = $ot->getUserInfo($deviceId, $token, withPaymentInstruments: true);
// UserInfoResponse: GSM, PIC, REAL_NAME, ID, KIN, EMAIL
// + PaymentInstrument[]: ID, VERIFIED, BALANCE, TYPE, NAME, EXPIRES

$balance = $ot->getInstrumentBalance($deviceId, $token, pinId: $instrumentId);
```

### Payment Flow (four steps)

```php
// 1. Initialize
$payment = $ot->initPayment($deviceId, $token);
// PaymentInitResponse: ID

// 2. Check (get fees)
$check = $ot->checkPayment(
    deviceId: $deviceId,
    token: $token,
    paymentId: $payment->id,
    amount: 2280,
    recipient: $recipientKin,
    recipientType: 'KIN',
    description: 'Taksa vhod',
    reason: 'monthly_fee',
    paymentInstrumentId: $pinId,
    show: 'KIN',
);
// PaymentCheckResponse: ID, AMOUNT, PAYMENT_INSTRUMENTS[{ID, NAME, TAX, TOTAL, STATUS}]

// 3. Send payment
$result = $ot->sendPayment(
    deviceId: $deviceId,
    token: $token,
    paymentId: $payment->id,
    amount: 2280,
    recipient: $recipientKin,
    recipientType: 'KIN',
    description: 'Taksa vhod',
    reason: 'monthly_fee',
    paymentInstrumentId: $pinId,
    show: 'KIN',
);
// PaymentSendResponse: STATE (2=processing, 3=success, 4=failure), STATE_TEXT, NO

// 4. Check status
$status = $ot->getPaymentStatus(
    deviceId: $deviceId,
    token: $token,
    paymentId: $payment->id,
);
// PaymentStatusResponse: STATE, STATE_TEXT, NO, paid_with details
```

### One Touch No Reg (payment without registration)

Allows card payments without user registration or token. Separate endpoints:

```php
// Send payment from unregistered user (redirects to ePay card entry page)
$paymentUrl = $ot->createNoRegPayment(
    deviceId: 'user@example.com',
    amount: 2280,
    recipient: $recipientKin,
    recipientType: 'KIN',
    description: 'Taksa vhod',
    reason: 'monthly_fee',
    show: 'KIN',
    saveCard: false,  // true to save card for future payments
);

// Check payment status
$status = $ot->getNoRegPaymentStatus(
    deviceId: 'user@example.com',
    paymentId: $paymentId,
);
// NoRegPaymentResponse: STATE, STATE_TEXT, NO
// When saveCard=true: includes PaymentInstrument (ID, CARD_TYPE, CARD_TYPE_DESCR)
// When saveCard=false: includes paid_with (CARD_TYPE, CARD_TYPE_DESCR, CARD_TYPE_COUNTRY)
```

Additional Response DTOs:
- `NoRegPaymentResponse` -- STATE, STATE_TEXT, NO, optional PaymentInstrument or PaidWith
- `PaidWith` -- CARD_TYPE, CARD_TYPE_DESCR, CARD_TYPE_COUNTRY

### APPCHECK

Optional request integrity check. When secret is configured, SDK generates it automatically:
- Sort all request parameters alphabetically by key
- Concatenate with newline: `KEY1VALUE1\nKEY2VALUE2\n...`
- `hmac_sha1_hex(concatenated, SECRET)`

## Billing API -- BillingHandler

Callback-based: ePay calls merchant server via HTTP GET. SDK parses requests, verifies checksums, and helps build responses.

### /pay/init -- Obligation Check

ePay asks: "How much does subscriber X owe?"

```php
$billing = new BillingHandler($config);
$initRequest = $billing->parseInitRequest($queryParams);
```

Parsing and verification:
1. Extract parameters: IDN, MERCHANTID, TYPE, TID, TOTAL, CHECKSUM
2. Verify CHECKSUM: sort params alphabetically, concatenate as `KEY1VALUE1\nKEY2VALUE2\n...`, HMAC-SHA1 with secret
3. Throw `InvalidResponseException` on mismatch

`InitRequest` fields:
- `idn`: string -- subscriber identifier
- `merchantId`: string
- `type`: BillingRequestType -- CHECK, BILLING, or DEPOSIT (enum)
- `tid`: ?string -- transaction ID (required for BILLING/DEPOSIT)
- `total`: ?int -- amount in stotinki (required for DEPOSIT)

Building response:

```php
// Subscriber found, has obligations:
$response = InitResponse::success(
    idn: '12345',
    shortDesc: 'Ivan Ivanov, ap. 12',
    longDesc: "Taksa vhod    50.00\nTaksa asansor 30.00",
    amount: 8000,
    validTo: new DateTimeImmutable('2026-05-01'),
    invoices: [
        new Invoice(idn: '12345.001', amount: 5000, shortDesc: 'Taksa vhod', validTo: ...),
        new Invoice(idn: '12345.002', amount: 3000, shortDesc: 'Taksa asansor', validTo: ...),
    ],
);

// No obligation:
$response = InitResponse::noObligation($idn);       // STATUS=62

// Invalid subscriber:
$response = InitResponse::invalidSubscriber($idn);  // STATUS=14

// Temporarily unavailable:
$response = InitResponse::unavailable();             // STATUS=80

// General error:
$response = InitResponse::error();                   // STATUS=96

return $response->toJson();
```

### /pay/confirm -- Payment Notification

ePay says: "Subscriber X paid."

```php
$confirmRequest = $billing->parseConfirmRequest($queryParams);
```

`ConfirmRequest` fields:
- `idn`: string
- `merchantId`: string
- `tid`: string -- transaction ID (DATE14 + STAN6 + AID6)
- `date`: DateTimeImmutable -- payment execution timestamp
- `total`: int -- amount in stotinki
- `type`: BillingPaymentType -- BILLING, PARTIAL, or DEPOSIT (enum)
- `invoices`: ?string -- comma-separated invoice IDNs, e.g. "12345.001,12345.002" (for multi-invoice payments; null for single-invoice)

Building response:

```php
$response = ConfirmResponse::success();     // STATUS=00
$response = ConfirmResponse::duplicate();   // STATUS=94
$response = ConfirmResponse::error();       // STATUS=96

return $response->toJson();
```

### BillingStatus Enum

```php
enum BillingStatus: string
{
    case Success = '00';            // /pay/init + /pay/confirm
    case InvalidAmount = '13';      // /pay/init only (deposit)
    case InvalidSubscriber = '14';  // /pay/init only
    case NoObligation = '62';       // /pay/init only
    case Unavailable = '80';        // /pay/init only
    case InvalidChecksum = '93';    // /pay/confirm only
    case Duplicate = '94';          // /pay/confirm only
    case GeneralError = '96';       // /pay/init + /pay/confirm
}
```

### CHECKSUM Calculation

1. Collect all input parameters (excluding CHECKSUM itself)
2. Sort alphabetically by parameter name
3. Concatenate: `PARAMNAME1VALUE1\nPARAMNAME2VALUE2\n...`
4. `hmac_sha1_hex(concatenated, secret)`

### Deposit Transactions

Deposit (prepayment) follows the same flow but with TYPE=DEPOSIT:
- `/pay/init` includes TOTAL (prepayment amount)
- Merchant returns STATUS=00 to approve or STATUS=13 for invalid amount
- `/pay/confirm` confirms the deposit was executed

### TID Structure

26 characters: DATE(14) + STAN(6) + AID(6)
- DATE: YYYYMMDDhhmmss
- STAN: service info (not processed by merchant)
- AID: payment source (7000xx = cash, others = electronic)
- TID remains identical for retransmissions of the same transaction -- merchant must detect duplicates

### Retry Behavior

ePay retries /pay/confirm notifications until merchant responds with STATUS=00 or STATUS=94. The TID stays constant across retries. Merchant must record only one payment per TID. STATUS=94 (Duplicate) is equivalent to STATUS=00 in terms of stopping retries.

### Payment Readiness Signal

When merchant returns STATUS=00 with amount > 0 on a TYPE=BILLING /pay/init request, this signals to ePay that payment may proceed. For TYPE=CHECK requests, the response is informational only.

## File Exchange -- ObligationFileGenerator

Generates obligation files for upload to mrcs.easypay.bg:

```php
$file = ObligationFileGenerator::create(session: '20260413120000')
    ->addObligation(subscriberId: '12345', amount: 8000, name: 'Ivan Ivanov')
    ->addObligation(subscriberId: '12346', amount: 6500, name: 'Petar Petrov');

$file->saveTo('/path/to/obligations.txt');
```

File format:
- Encoding: Windows CP-1251
- Delimiter: `|` (pipe)
- Fields: subscriber number | amount (decimal) | name (optional) | address (optional) | due date (optional)
- Session header: `session=YYYYMMDDHHmmss`
- One line per subscriber, no duplicates

## IdnGenerator

Helper for generating and validating subscriber IDN numbers:

```php
IdnGenerator::generate(prefix: '001', subscriberId: '0012');  // '0010012'
IdnGenerator::padded(prefix: '001', subscriberId: 12, length: 10);  // '0010000012'
IdnGenerator::parse('0010000012', prefixLength: 3);  // ['prefix' => '001', 'subscriberId' => '0000012']
IdnGenerator::validate('ABC123');  // throws -- digits only
```

Constraints from ePay: varchar(64), digits only.

## RsaKeyGenerator

Generates RSA key pairs for merchants who need RSA signing:

```php
$result = RsaKeyGenerator::generate(
    keyBits: 2048,
    passphrase: 'secure_password',
);

$result->privateKey;  // PEM string
$result->publicKey;   // PEM string (upload to ePay profile)
$result->saveToDirectory('/path/to/keys');
```

## Security

### Immutability

All Request and Response objects are `readonly` classes. No mutable state after construction.

### Sensitive Data Protection

- `MerchantConfig`: `secret` and `privateKey` are private properties, redacted in `__debugInfo__()`, both `__serialize()` and `__unserialize()` throw exception
- `InvalidResponseException`: redacts CHECKSUM, ENCODED, SIGNATURE, TOKEN in context
- All Response DTOs: `__debugInfo__()` hides sensitive fields, `toSafeArray()` returns redacted representation for logging
- Token values (One Touch) are never included in log-safe representations

### Input Validation (fail-fast)

Every object validates at construction time:
- `MerchantConfig`: merchantId, secret, key loading
- Request objects: amount > 0.01, invoice format, IDN digits only, IBAN/BIC format, date formats
- Signing: key validity checked at load time

### Cryptographic Verification

- HMAC comparison uses `hash_equals()` (constant-time, prevents timing attacks)
- CHECKSUM verified before any data processing in all handlers
- Billing: `parseInitRequest()` and `parseConfirmRequest()` throw `InvalidResponseException` on checksum mismatch
- WEB notifications: `handleNotification()` verifies CHECKSUM before parsing payload
- One Touch: APPCHECK generated and verified automatically

### Verification at Every Step

No data is trusted until its integrity is verified. The SDK enforces this by making verification non-optional -- parse methods verify before returning parsed data. There is no "skip verification" option.

## Laravel Integration

Separate namespace: `Ux2Dev\Epay\Laravel`. Does not modify core classes.

### ServiceProvider

- Registers `WebClient`, `OneTouchClient`, `BillingHandler` as singletons
- Multi-tenancy: resolves merchant config by name from config file
- Auto-wires PSR-18 HTTP client for One Touch

### Config (config/epay.php)

```php
return [
    'default' => 'main',
    'merchants' => [
        'main' => [
            'merchant_id' => env('EPAY_MERCHANT_ID'),
            'secret' => env('EPAY_SECRET'),
            'environment' => 'production',
            'currency' => 'EUR',
            'signing_method' => 'hmac',
            'private_key' => null,
            'private_key_passphrase' => null,
            'url_ok' => env('EPAY_URL_OK'),
            'url_cancel' => env('EPAY_URL_CANCEL'),
            'notification_url' => env('EPAY_NOTIFICATION_URL'),
        ],
    ],
];
```

### Facade

```php
Epay::merchant('building_2')->web()->createPaymentRequest(...);
Epay::web()->createPaymentRequest(...);  // uses default merchant
Epay::billing()->parseInitRequest($request->query());
Epay::oneTouch()->getAuthorizationUrl(...);
```

### Routes (optional, publish with vendor:publish)

- `POST /epay/notify` -- WEB API callback notification
- `GET /epay/billing/init` -- Billing obligation check
- `GET /epay/billing/confirm` -- Billing payment confirmation

### Middleware

- `VerifyEpayChecksum` -- verifies CHECKSUM/ENCODED on incoming requests before controller logic

### Events

- `PaymentReceived` -- successful WEB callback (STATUS=PAID)
- `PaymentDenied` -- denied payment (STATUS=DENIED)
- `PaymentExpired` -- expired payment (STATUS=EXPIRED)
- `BillingObligationChecked` -- /pay/init processed
- `BillingPaymentConfirmed` -- /pay/confirm processed

### Artisan Commands

- `epay:generate-key` -- generates RSA key pair
- `epay:generate-obligations` -- generates obligation file for file exchange

## Testing

Pest PHP. Full coverage of every class.

### Test Structure

```
tests/
├── fixtures/
│   ├── test_private_key.pem
│   ├── test_private_key_encrypted.pem
│   └── test_public_key.pem
├── Config/
│   └── MerchantConfigTest.php
├── Signing/
│   ├── HmacSignerTest.php
│   └── RsaSignerTest.php
├── Web/
│   ├── WebClientTest.php
│   ├── Request/ (per request type)
│   ├── Response/
│   └── Notification/
├── OneTouch/
│   ├── OneTouchClientTest.php
│   ├── Request/
│   └── Response/
├── Billing/
│   ├── BillingHandlerTest.php
│   ├── Request/
│   └── Response/
├── KeyGenerator/
│   └── RsaKeyGeneratorTest.php
├── IdnGenerator/
│   └── IdnGeneratorTest.php
├── Pest.php
└── TestCase.php
```

### What We Test

- **Config**: construction validation, invalid values throw, serialization blocked, debugInfo redacted
- **Signing**: HMAC round-trip (sign -> verify), RSA round-trip, constant-time comparison, tampered data rejected, wrong passphrase throws, invalid key throws
- **WEB requests**: correct form fields generated, base64 encoding valid, CHECKSUM matches, optional fields conditional, gateway URL matches environment
- **WEB notifications**: valid callback parsed, CHECKSUM verified, tampered callback rejected, multiple invoices in one callback, correct response generated (OK/ERR/NO)
- **One Touch**: mock HTTP responses, each endpoint returns correct DTO, APPCHECK generated correctly, invalid token throws, error responses handled
- **Billing**: valid /pay/init parsed, invalid CHECKSUM throws, JSON responses with correct STATUS codes, deposit transactions, partial payments, duplicate notifications (STATUS=94), all BillingStatus values tested
- **Security**: sensitive data absent from exceptions, not serializable, not in debugInfo, hash_equals used (not ==)
- **KeyGenerator**: generates valid key pair, passphrase encryption works, key bits configurable
- **IdnGenerator**: valid IDN generated, digits-only enforced, parse round-trip, padding works

### Custom Pest Expectations

```php
expect($response)->toHaveValidChecksum($secret);
expect($config)->toRedactSensitiveData();
expect($request)->toGenerateValidFormFields();
```

## Dependencies

### Core (composer.json require)

- `php`: `^8.2`
- `psr/http-client`: `^1.0` (for One Touch)
- `psr/http-factory`: `^1.0` (for One Touch)
- `ext-openssl`: `*` (for RSA signing)
- `ext-json`: `*` (for Billing JSON responses)
- `ext-mbstring`: `*` (for CP-1251 conversion)

### Dev

- `pestphp/pest`: `^3.0`
- `guzzlehttp/guzzle`: `^7.0` (for One Touch tests with mock handler)
- `orchestra/testbench`: `^9.0` (for Laravel integration tests)

### Laravel (suggest)

- `illuminate/support`: `^11.0|^12.0`
