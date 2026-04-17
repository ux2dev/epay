# ePay.bg / EasyPay PHP SDK

> **Warning:** This is a developer testing version of the library -- use at your own risk.

A framework-agnostic PHP SDK for the ePay.bg and EasyPay payment gateway. Covers all three APIs: WEB, One Touch, and Billing. Works with plain PHP or Laravel.

## Requirements

- PHP 8.2 or higher
- OpenSSL extension
- JSON extension
- mbstring extension
- PSR-18 HTTP client (for One Touch API only, e.g. Guzzle)

## Installation

```bash
composer require ux2dev/epay-easypay
```

For One Touch API, you also need a PSR-18 HTTP client:

```bash
composer require guzzlehttp/guzzle
```

## Quick Start

### Plain PHP

```php
use Ux2Dev\Epay\Config\MerchantConfig;
use Ux2Dev\Epay\Enum\Environment;
use Ux2Dev\Epay\Web\WebClient;

$config = new MerchantConfig(
    merchantId: '1000000000',       // Your KIN from ePay.bg profile
    secret: 'your_secret_word',     // Secret word from ePay.bg profile
    environment: Environment::Production,
);

$web = new WebClient($config);

$request = $web->createPaymentRequest(
    invoice: 'INV-001',
    amount: '22.80',
    expirationDate: '01.08.2026',
    description: 'Monthly fee',
);

// Render a form that submits to ePay.bg
echo '<form action="' . $request->getGatewayUrl() . '" method="POST">';
foreach ($request->toArray() as $name => $value) {
    echo '<input type="hidden" name="' . $name . '" value="' . $value . '">';
}
echo '<button type="submit">Pay with ePay.bg</button>';
echo '</form>';
```

### Laravel

```php
// In your controller
use Ux2Dev\Epay\Laravel\EpayFacade as Epay;

$request = Epay::web()->createPaymentRequest(
    invoice: 'INV-001',
    amount: '22.80',
    expirationDate: '01.08.2026',
);

return view('payment', [
    'gatewayUrl' => $request->getGatewayUrl(),
    'fields' => $request->toArray(),
]);
```

## Configuration

### MerchantConfig

Every client requires a `MerchantConfig` instance. This is an immutable, readonly object that validates all inputs at construction time.

```php
use Ux2Dev\Epay\Config\MerchantConfig;
use Ux2Dev\Epay\Enum\Currency;
use Ux2Dev\Epay\Enum\Environment;
use Ux2Dev\Epay\Enum\SigningMethod;

$config = new MerchantConfig(
    merchantId: '1000000000',                    // Required. Your KIN from ePay.bg
    secret: 'your_secret_word',                  // Required. Secret word from ePay.bg
    environment: Environment::Production,        // Required. Production or Development
    currency: Currency::EUR,                     // Optional. Default: EUR. Also: BGN, USD
    signingMethod: SigningMethod::HmacSha1,      // Optional. Default: HmacSha1. Also: Rsa
    privateKey: null,                            // Optional. PEM string or file path. Required when signingMethod is Rsa
    privateKeyPassphrase: null,                  // Optional. Passphrase for encrypted private key
);
```

**Environments:**

| Environment | Gateway URL | One Touch Base URL |
|------------|-------------|-------------------|
| `Environment::Development` | `https://demo.epay.bg/` | `https://demo.epay.bg/xdev/api` |
| `Environment::Production` | `https://www.epay.bg/` | `https://www.epay.bg/xdev/api` |

Use `Environment::Development` for testing. ePay.bg provides a demo environment at `https://demo.epay.bg/` where you can test payments without real money.

**Security:** `MerchantConfig` protects sensitive data. The `secret` and `privateKey` fields are private and accessible only through getter methods. They are redacted in `var_dump()` output and the object cannot be serialized.

### Laravel Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=epay-config
```

This creates `config/epay.php`:

```php
return [
    'default' => 'main',

    'merchants' => [
        'main' => [
            'merchant_id' => env('EPAY_MERCHANT_ID'),
            'secret' => env('EPAY_SECRET'),
            'environment' => env('EPAY_ENVIRONMENT', 'production'),
            'currency' => env('EPAY_CURRENCY', 'EUR'),
            'signing_method' => env('EPAY_SIGNING_METHOD', 'hmac'),
            'private_key' => env('EPAY_PRIVATE_KEY'),
            'private_key_passphrase' => env('EPAY_PRIVATE_KEY_PASSPHRASE'),
            'url_ok' => env('EPAY_URL_OK'),
            'url_cancel' => env('EPAY_URL_CANCEL'),
            'notification_url' => env('EPAY_NOTIFICATION_URL'),
        ],
    ],
];
```

Add to your `.env`:

```
EPAY_MERCHANT_ID=1000000000
EPAY_SECRET=your_secret_word
EPAY_ENVIRONMENT=development
EPAY_CURRENCY=EUR
EPAY_URL_OK=https://yoursite.com/payment/success
EPAY_URL_CANCEL=https://yoursite.com/payment/cancel
```

#### Multi-tenancy (Multiple Merchants)

Add additional merchants to the config:

```php
'merchants' => [
    'main' => [
        'merchant_id' => env('EPAY_MERCHANT_ID'),
        'secret' => env('EPAY_SECRET'),
        // ...
    ],
    'building_2' => [
        'merchant_id' => env('EPAY_BUILDING2_MERCHANT_ID'),
        'secret' => env('EPAY_BUILDING2_SECRET'),
        'environment' => 'production',
        'currency' => 'EUR',
        'signing_method' => 'hmac',
    ],
],
```

Use a specific merchant:

```php
use Ux2Dev\Epay\Laravel\EpayFacade as Epay;

// Default merchant
Epay::web()->createPaymentRequest(...);

// Specific merchant
Epay::merchant('building_2')->web()->createPaymentRequest(...);
Epay::merchant('building_2')->billing()->parseInitRequest(...);
```

## WEB API

The WEB API handles browser-based payments. The flow is:

1. Your server creates a payment request with signed data
2. You render an HTML form that POSTs to ePay.bg
3. The customer pays on ePay.bg
4. ePay.bg sends a callback (notification) to your server
5. ePay.bg redirects the customer back to your site

### Creating a WebClient

```php
use Ux2Dev\Epay\Web\WebClient;

$web = new WebClient($config);
```

In Laravel:

```php
$web = Epay::web();
```

### Payment Request (Standard)

Creates a signed payment request using the ENCODED + CHECKSUM flow. This is the most common payment method.

```php
$request = $web->createPaymentRequest(
    invoice: 'INV-001',                          // Required. Your invoice number
    amount: '22.80',                             // Required. Amount > 0.01
    expirationDate: '01.08.2026',                // Required. Format: DD.MM.YYYY
    description: 'Monthly maintenance fee',      // Optional. Max 100 characters
    encoding: 'utf-8',                           // Optional. Set to 'utf-8' for UTF-8 descriptions
    email: null,                                 // Optional. Merchant email (alternative to MIN)
    discount: null,                              // Optional. Card BIN discount rules
    urlOk: 'https://yoursite.com/success',       // Optional. Redirect URL on success
    urlCancel: 'https://yoursite.com/cancel',    // Optional. Redirect URL on cancel
);
```

The returned `PaymentRequest` object contains everything you need to render the payment form:

```php
$gatewayUrl = $request->getGatewayUrl();    // https://www.epay.bg/ or https://demo.epay.bg/
$formFields = $request->toArray();           // ['PAGE' => 'paylogin', 'ENCODED' => '...', 'CHECKSUM' => '...', ...]
```

Render the form in your HTML:

```html
<form action="{{ $gatewayUrl }}" method="POST">
    @foreach($formFields as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <button type="submit">Pay Now</button>
</form>
```

### Direct Card Payment Request

Same as the standard payment, but the customer enters card details directly on a page hosted by ePay.bg (no ePay.bg login required). Supports a language parameter.

```php
$request = $web->createDirectPaymentRequest(
    invoice: 'INV-001',
    amount: '22.80',
    expirationDate: '01.08.2026',
    lang: 'en',                    // 'bg' or 'en'. Default: 'bg'
    description: 'Monthly fee',
    urlOk: 'https://yoursite.com/success',
    urlCancel: 'https://yoursite.com/cancel',
);

// Same form rendering as above
// PAGE will be 'credit_paydirect' instead of 'paylogin'
```

### Bank Transfer Request

Initiates a bank transfer. Does not use ENCODED/CHECKSUM. Fields are sent directly.

```php
$request = $web->createBankTransferRequest(
    merchant: 'Company Name Ltd.',               // Required. Merchant name
    iban: 'BG80BNBG96611020345678',             // Required. Valid IBAN
    bic: 'BNBGBGSD',                            // Required. Valid BIC
    total: '22.80',                              // Required. Amount > 0.01
    statement: 'Monthly fee April 2026',         // Required. Payment statement
    pstatement: '123456',                        // Required. Exactly 6 digits
    urlOk: 'https://yoursite.com/success',
    urlCancel: 'https://yoursite.com/cancel',
);
```

### Simple Payment Request

A simplified variant that sends fields directly without encoding. For merchants that do not need the ENCODED/CHECKSUM flow.

```php
$request = $web->createSimplePaymentRequest(
    invoice: 'INV-001',                    // Required
    total: '22.80',                        // Required. Amount > 0.01
    description: 'Monthly fee',            // Optional
    encoding: 'utf-8',                     // Optional
    urlOk: 'https://yoursite.com/success',
    urlCancel: 'https://yoursite.com/cancel',
);
```

### Handling Payment Notifications (Callbacks)

After a customer pays, ePay.bg sends an HTTP POST to your notification URL with `ENCODED` and `CHECKSUM` parameters. The SDK verifies the CHECKSUM before parsing.

```php
// In your callback endpoint (e.g. POST /epay/notify)
$result = $web->handleNotification($_POST);

foreach ($result->items() as $item) {
    // $item->invoice   - Your invoice number
    // $item->status    - PaymentStatus::Paid, PaymentStatus::Denied, or PaymentStatus::Expired
    // $item->payTime   - DateTimeImmutable (only when Paid)
    // $item->stan      - Transaction number (only when Paid)
    // $item->bcode     - Authorization code (only when Paid)
    // $item->amount    - Discounted amount (only when discount applied)
    // $item->bin       - Card BIN (only when discount applied)

    if ($item->status === \Ux2Dev\Epay\Enum\PaymentStatus::Paid) {
        // Mark invoice as paid in your database
        $item->acknowledge();   // Tell ePay: OK, received
    } else {
        $item->notFound();      // Tell ePay: unknown invoice (or use reject() to retry later)
    }
}

// Return the response to ePay.bg
header('Content-Type: text/plain');
echo $result->toHttpResponse();
```

In Laravel:

```php
// routes/web.php
Route::post('/epay/notify', function (Request $request) {
    $result = Epay::web()->handleNotification($request->all());

    foreach ($result->items() as $item) {
        if ($item->status === PaymentStatus::Paid) {
            Invoice::where('number', $item->invoice)->update(['paid' => true]);
            $item->acknowledge();
        } else {
            $item->notFound();
        }
    }

    return response($result->toHttpResponse(), 200)
        ->header('Content-Type', 'text/plain');
});
```

**Response statuses:**

| Method | ePay Status | Meaning |
|--------|------------|---------|
| `$item->acknowledge()` | OK | Received successfully. ePay stops sending. |
| `$item->reject()` | ERR | Error processing. ePay will retry. |
| `$item->notFound()` | NO | Unknown invoice. ePay stops sending. |

**Retry schedule:** ePay retries on ERR or no response for up to 30 days: 5 times under 1 minute, 4 times every 15 minutes, 5 times every hour, 6 times every 3 hours, 4 times every 6 hours, 1 time daily.

### RSA Signing (Optional)

For additional security, you can sign requests with RSA in addition to HMAC-SHA1. The HMAC CHECKSUM is always present; the RSA SIGNATURE is additive.

```php
$config = new MerchantConfig(
    merchantId: '1000000000',
    secret: 'your_secret_word',
    environment: Environment::Production,
    signingMethod: SigningMethod::Rsa,
    privateKey: file_get_contents('/path/to/private_key.pem'),
    privateKeyPassphrase: 'optional_passphrase',
);

$web = new WebClient($config);
$request = $web->createPaymentRequest(...);

// $request->toArray() will now include both CHECKSUM and SIGNATURE
```

Generate an RSA key pair:

```php
use Ux2Dev\Epay\KeyGenerator\RsaKeyGenerator;

$keys = RsaKeyGenerator::generate(
    keyBits: 2048,
    passphrase: 'optional_passphrase',
);

$keys->saveToDirectory('/path/to/keys');
// Creates: epay_private.key and epay_public.key
// Upload epay_public.key to your ePay.bg merchant profile
```

In Laravel:

```bash
php artisan epay:generate-key --output=/path/to/keys --passphrase=optional
```

## Billing API

The Billing API handles periodic payments (utility bills, maintenance fees, subscriptions). The flow is the opposite of the WEB API: ePay.bg calls YOUR server.

1. A customer goes to EasyPay or ePay.bg and enters their subscriber number (IDN)
2. ePay.bg calls your `/pay/init` endpoint: "How much does subscriber X owe?"
3. Your server responds with the obligation amount
4. The customer pays
5. ePay.bg calls your `/pay/confirm` endpoint: "Subscriber X paid"
6. Your server confirms

### Creating a BillingHandler

```php
use Ux2Dev\Epay\Billing\BillingHandler;

$billing = new BillingHandler($config);
```

In Laravel:

```php
$billing = Epay::billing();
```

### Handling /pay/init (Obligation Check)

When ePay.bg asks "How much does subscriber X owe?":

```php
// Your endpoint receives GET parameters from ePay.bg
// e.g. GET /pay/init?IDN=12345&MERCHANTID=0000334&TYPE=CHECK&CHECKSUM=...

$initRequest = $billing->parseInitRequest($_GET);
// CHECKSUM is automatically verified. Throws InvalidResponseException on mismatch.

// $initRequest->idn          - Subscriber identifier (string)
// $initRequest->merchantId   - Your merchant ID (string)
// $initRequest->type         - BillingRequestType::Check, Billing, or Deposit
// $initRequest->tid          - Transaction ID (only for Billing/Deposit)
// $initRequest->total        - Amount in stotinki (only for Deposit)
```

Build and return the response:

```php
use Ux2Dev\Epay\Billing\Response\InitResponse;
use Ux2Dev\Epay\Billing\Response\Invoice;

// Find the subscriber in your database
$apartment = Apartment::findByEpayId($initRequest->idn);

if (!$apartment) {
    header('Content-Type: application/json');
    echo InitResponse::invalidSubscriber($initRequest->idn)->toJson();
    return;
}

$obligations = $apartment->unpaidObligations();

if ($obligations->isEmpty()) {
    header('Content-Type: application/json');
    echo InitResponse::noObligation($initRequest->idn)->toJson();
    return;
}

// Return the obligations
header('Content-Type: application/json');
echo InitResponse::success(
    idn: $initRequest->idn,
    shortDesc: $apartment->ownerName . ', ap. ' . $apartment->number,
    amount: $obligations->totalInStotinki(),   // e.g. 8000 = 80.00 lv
    validTo: new DateTimeImmutable('2026-05-01'),
    longDesc: "Maintenance fee   50.00\nElevator          30.00\nTotal             80.00",
    invoices: [
        new Invoice(
            idn: $initRequest->idn . '.001',
            amount: 5000,
            shortDesc: 'Maintenance fee',
            validTo: new DateTimeImmutable('2026-05-01'),
        ),
        new Invoice(
            idn: $initRequest->idn . '.002',
            amount: 3000,
            shortDesc: 'Elevator',
            validTo: new DateTimeImmutable('2026-05-01'),
        ),
    ],
)->toJson();
```

**Available response methods:**

| Method | STATUS | When to use |
|--------|--------|-------------|
| `InitResponse::success(...)` | 00 | Subscriber found, has obligations |
| `InitResponse::noObligation($idn)` | 62 | Subscriber found, no obligations |
| `InitResponse::invalidSubscriber($idn)` | 14 | Unknown subscriber |
| `InitResponse::invalidAmount()` | 13 | Invalid deposit amount |
| `InitResponse::unavailable()` | 80 | Temporarily unavailable |
| `InitResponse::error()` | 96 | General error |

### Handling /pay/confirm (Payment Confirmation)

When ePay.bg tells you "Subscriber X paid":

```php
// GET /pay/confirm?IDN=12345&MERCHANTID=0000334&TID=...&DATE=...&TOTAL=16600&TYPE=BILLING&CHECKSUM=...

$confirmRequest = $billing->parseConfirmRequest($_GET);
// CHECKSUM is automatically verified.

// $confirmRequest->idn         - Subscriber identifier
// $confirmRequest->merchantId  - Your merchant ID
// $confirmRequest->tid         - Transaction ID (26 chars: DATE14 + STAN6 + AID6)
// $confirmRequest->date        - Payment timestamp (DateTimeImmutable)
// $confirmRequest->total       - Amount in stotinki (int)
// $confirmRequest->type        - BillingPaymentType::Billing, Partial, or Deposit
// $confirmRequest->invoices    - Comma-separated invoice IDNs or null
```

Build and return the response:

```php
use Ux2Dev\Epay\Billing\Response\ConfirmResponse;

// Check for duplicate (same TID)
if (Payment::where('tid', $confirmRequest->tid)->exists()) {
    header('Content-Type: application/json');
    echo ConfirmResponse::duplicate()->toJson();   // STATUS=94
    return;
}

// Record the payment
Payment::create([
    'idn' => $confirmRequest->idn,
    'tid' => $confirmRequest->tid,
    'total' => $confirmRequest->total,
    'paid_at' => $confirmRequest->date,
]);

header('Content-Type: application/json');
echo ConfirmResponse::success()->toJson();   // STATUS=00
```

**Available response methods:**

| Method | STATUS | When to use |
|--------|--------|-------------|
| `ConfirmResponse::success()` | 00 | Payment recorded |
| `ConfirmResponse::duplicate()` | 94 | Already processed (same TID) |
| `ConfirmResponse::invalidChecksum()` | 93 | Bad checksum |
| `ConfirmResponse::error()` | 96 | General error |

### CHECKSUM Calculation (Billing)

The Billing API uses a different CHECKSUM algorithm than the WEB API. The SDK handles this automatically, but for reference:

1. Collect all GET parameters except CHECKSUM
2. Sort alphabetically by parameter name
3. Concatenate as `KEY1VALUE1\nKEY2VALUE2\n...` (no separator between key and value, newline between pairs)
4. HMAC-SHA1 with your secret word

### Subscriber IDN Numbers

The IDN (subscriber identifier) is your internal number. ePay requires it to be digits only, max 64 characters. The SDK provides a helper:

```php
use Ux2Dev\Epay\IdnGenerator\IdnGenerator;

// Simple concatenation
$idn = IdnGenerator::generate('001', '0012');        // '0010012'

// Fixed-length with padding
$idn = IdnGenerator::padded('001', 12, 10);          // '0010000012'

// Parse back
$parts = IdnGenerator::parse('0010000012', 3);
// ['prefix' => '001', 'subscriberId' => '0000012']

// Validate
IdnGenerator::validate('12345');    // OK
IdnGenerator::validate('ABC123');   // Throws ConfigurationException
```

### LONGDESC Formatting

The LONGDESC field in Billing responses uses special escape sequences:

```php
use Ux2Dev\Epay\Billing\Formatter\LongDescFormatter;

// Encode for ePay
$encoded = LongDescFormatter::encode("Line 1\nLine 2\n--------\nCol1\tCol2");
// Result: 'Line 1\nLine 2\n\$\nCol1\tCol2'

// Decode from ePay
$decoded = LongDescFormatter::decode('Line 1\nLine 2');

// Validate line length (max 110 characters per line)
LongDescFormatter::validate($text);   // Throws ConfigurationException if any line > 110 chars
```

### Obligation File Exchange

For batch processing, generate obligation files for upload to `mrcs.easypay.bg`:

```php
use Ux2Dev\Epay\Billing\FileExchange\ObligationFileGenerator;

$file = ObligationFileGenerator::create('20260413120000')   // Session: YYYYMMDDHHmmss
    ->addObligation(subscriberId: '12345', amount: 8000, name: 'Ivan Ivanov')
    ->addObligation(subscriberId: '12346', amount: 6500, name: 'Petar Petrov')
    ->addObligation(
        subscriberId: '12347',
        amount: 12000,
        name: 'Maria Georgieva',
        address: 'Sofia, ul. Rakovski 1',
        dueDate: '20260501',
    );

$file->saveTo('/path/to/obligations.txt');
```

The file is generated in Windows CP-1251 encoding with pipe (`|`) delimiters, as required by ePay.bg. Amounts are in stotinki (8000 = 80.00 lv). Each subscriber can appear only once.

In Laravel:

```bash
php artisan epay:generate-obligations /path/to/output.txt --session=20260413120000
```

## One Touch API

The One Touch API enables tokenized payments for mobile and web applications. Instead of redirecting to ePay.bg each time, the customer authorizes once, and you receive a token for future payments.

### Creating an OneTouchClient

```php
use Ux2Dev\Epay\OneTouch\OneTouchClient;
use GuzzleHttp\Client;

$oneTouch = new OneTouchClient($config, new Client());
```

In Laravel:

```php
$oneTouch = Epay::oneTouch();
```

### Token Acquisition (Three Steps)

**Step 1: Generate authorization URL**

Redirect the customer to this URL. They will log in to ePay.bg and authorize your application.

```php
$authUrl = $oneTouch->getAuthorizationUrl(
    deviceId: 'user@example.com',    // Unique device/user identifier
    key: bin2hex(random_bytes(16)),   // Unique key for this authorization
    userType: null,                   // 1 = ePay users only, 2 = cards only, null = both
    deviceName: 'My App',            // Optional device info
    os: 'Web',
);

// Redirect the customer
header('Location: ' . $authUrl);
```

**Step 2: Poll for authorization code**

After the customer authorizes, poll for the code. Recommended: every 20-30 seconds, up to 30 minutes.

```php
$response = $oneTouch->getCode(
    deviceId: 'user@example.com',
    key: 'the_same_key_from_step_1',
);

if ($response->status === 'OK') {
    $code = $response->code;   // Use this in Step 3
}
// If status is not 'OK', the customer hasn't authorized yet. Retry later.
```

**Step 3: Exchange code for token**

```php
$token = $oneTouch->getToken(
    deviceId: 'user@example.com',
    code: $code,
);

// Save these for future use:
// $token->token     - The access token
// $token->expires   - Unix timestamp when token expires
// $token->kin       - Customer's KIN
// $token->username  - Customer's username
// $token->realName  - Customer's real name
```

### Token Management

```php
// Revoke a token
$oneTouch->invalidateToken(
    deviceId: 'user@example.com',
    token: $savedToken,
);
```

### User Information

```php
$userInfo = $oneTouch->getUserInfo(
    deviceId: 'user@example.com',
    token: $savedToken,
    withPaymentInstruments: true,   // Include cards and accounts
);

// $userInfo->gsm       - Phone number
// $userInfo->realName   - Full name
// $userInfo->kin        - Customer KIN
// $userInfo->email      - Email

foreach ($userInfo->paymentInstruments as $instrument) {
    // $instrument->id         - Instrument ID (use for payments)
    // $instrument->name       - e.g. "Visa ****1234"
    // $instrument->type       - 1 = card, 2 = micro-account
    // $instrument->balance    - Balance in stotinki
    // $instrument->verified   - Whether verified
    // $instrument->expires    - Expiration date
}
```

### Payment Flow (Four Steps)

**Step 1: Initialize payment**

```php
$payment = $oneTouch->initPayment(
    deviceId: 'user@example.com',
    token: $savedToken,
);

$paymentId = $payment->id;
```

**Step 2: Check payment (get fees)**

```php
$check = $oneTouch->checkPayment(
    deviceId: 'user@example.com',
    token: $savedToken,
    paymentId: $paymentId,
    amount: 2280,                        // Amount in stotinki (22.80 lv)
    recipient: '8888',                   // Recipient KIN
    recipientType: 'KIN',
    description: 'Monthly maintenance fee',
    reason: 'monthly_fee',
    paymentInstrumentId: $instrumentId,  // From getUserInfo
    show: 'KIN',                         // What recipient sees: KIN, GSM, EMAIL, NAME
);

// $check->amount   - Payment amount
// Review fees per instrument before sending
```

**Step 3: Send payment**

```php
$result = $oneTouch->sendPayment(
    deviceId: 'user@example.com',
    token: $savedToken,
    paymentId: $paymentId,
    amount: 2280,
    recipient: '8888',
    recipientType: 'KIN',
    description: 'Monthly maintenance fee',
    reason: 'monthly_fee',
    paymentInstrumentId: $instrumentId,
    show: 'KIN',
);

// $result->state      - 2 = processing, 3 = success, 4 = failure
// $result->stateText  - Human-readable status
// $result->no         - Payment number
```

**Step 4: Check payment status**

```php
$status = $oneTouch->getPaymentStatus(
    deviceId: 'user@example.com',
    token: $savedToken,
    paymentId: $paymentId,
);

if ($status->state === 3) {
    // Payment successful
}
```

### No-Registration Payments

Allow card payments without user registration or token. The customer is redirected to ePay.bg to enter card details.

```php
$paymentUrl = $oneTouch->createNoRegPaymentUrl(
    deviceId: 'user@example.com',
    amount: 2280,
    recipient: '8888',
    recipientType: 'KIN',
    description: 'Monthly maintenance fee',
    reason: 'monthly_fee',
    show: 'KIN',
    saveCard: false,    // true to save card for future payments
);

// Redirect the customer
header('Location: ' . $paymentUrl);

// Later, check the payment status. The status endpoint requires the same
// params used at create (they feed into CHECKSUM), plus the payment `id`
// echoed back in ePay's redirect.
$status = $oneTouch->getNoRegPaymentStatus(
    deviceId: 'user@example.com',
    paymentId: 'payment_id',
    amount: 2280,
    recipient: '8888',
    recipientType: 'KIN',
    description: 'Monthly maintenance fee',
    reason: 'monthly_fee',
);

// $status->state              - 2 = pending, 3 = success, 4 = failure
// $status->stateText          - Human-readable (nullable)
// $status->no                 - Payment number (nullable)
// $status->token              - Reusable token when SAVECARD=1 (nullable)
// $status->paidWith           - Card details (when saveCard=false)
// $status->paymentInstrument  - Saved instrument (when saveCard=true)
```

#### NoReg redirect callback

After the customer pays, ePay redirects them to your configured `REPLY_ADDRESS`. The query string looks like:

```
?ret=authok&authok=1&deviceid=<deviceId>&id=<paymentId>
```

The authorization flow (ePay account) redirects to the same URL but **without** an `id` param. Distinguish the two flows by checking for `id`:

```php
if (isset($_GET['id'])) {
    // NoReg card payment: call getNoRegPaymentStatus() to fetch state + token
} else {
    // Auth flow: exchange saved KEY for code, then code for token
}
```

### Signing

The SDK signs requests automatically:

- **APPCHECK** (HMAC-SHA1, sorted params, no trailing newline) — auth flow, user info, registered payments
- **CHECKSUM** (HMAC-SHA1, sorted params, **trailing newline**) — noreg create and noreg status

You do not need to compute these yourself.

## Laravel Routes

The SDK ships ready-to-use routes for the three callback types. Enable them in config:

```php
// config/epay.php
'routes' => [
    'enabled' => env('EPAY_ROUTES_ENABLED', false),
    'prefix' => env('EPAY_ROUTES_PREFIX', 'epay'),
    'middleware' => [],     // e.g. ['throttle:60,1']
],
```

With `enabled = true` and the default prefix `epay`, the following routes are registered:

| Method | URI | Controller | Purpose |
|--------|-----|------------|---------|
| `POST` | `/epay/notify` | `WebNotificationController` | WEB API payment notifications |
| `GET` | `/epay/billing/init` | `BillingController@init` | EasyPay obligation check |
| `GET` | `/epay/billing/confirm` | `BillingController@confirm` | EasyPay payment confirmation |
| `GET` | `/epay/callback` | `OneTouchCallbackController` | One Touch auth + noreg redirect |

### Billing resolvers

The Billing controller can't know about your domain's obligations, so you register closures in a service provider:

```php
use Ux2Dev\Epay\Laravel\EpayFacade as Epay;
use Ux2Dev\Epay\Billing\Request\InitRequest;
use Ux2Dev\Epay\Billing\Request\ConfirmRequest;
use Ux2Dev\Epay\Billing\Response\InitResponse;
use Ux2Dev\Epay\Billing\Response\ConfirmResponse;

// AppServiceProvider::boot()
Epay::billingInitUsing(function (InitRequest $req): InitResponse {
    $obligations = Obligation::where('idn', $req->idn)->unpaid()->get();

    if ($obligations->isEmpty()) {
        return InitResponse::noObligation($req->idn);
    }

    return InitResponse::success(
        idn: $req->idn,
        shortDesc: 'Задължения на ' . $req->idn,
        amount: $obligations->sum('amount'),
        validTo: now()->addDays(30),
    );
});

Epay::billingConfirmUsing(function (ConfirmRequest $req): ConfirmResponse {
    if (Payment::where('tid', $req->tid)->exists()) {
        return ConfirmResponse::duplicate();
    }

    Payment::recordFromBilling($req);
    return ConfirmResponse::success();
});
```

The controller throws `LogicException` if a request arrives and no resolver is registered — fail loud rather than silently returning empty responses.

### Listening to callbacks

Every controller dispatches events; wire them in your `EventServiceProvider`:

```php
use Ux2Dev\Epay\Laravel\Events\NoRegPaymentCallback;
use Ux2Dev\Epay\Laravel\Events\OneTouchAuthorizationCallback;
use Ux2Dev\Epay\Laravel\Events\PaymentReceived;

protected $listen = [
    PaymentReceived::class => [MarkOrderPaid::class],
    NoRegPaymentCallback::class => [FetchNoRegStatus::class],
    OneTouchAuthorizationCallback::class => [ExchangeKeyForToken::class],
];
```

The One Touch callback **does not** auto-exchange the key for a token — that requires access to the app-stored KEY used when generating the auth URL. Your listener decides what to do:

```php
final class ExchangeKeyForToken
{
    public function handle(OneTouchAuthorizationCallback $event): void
    {
        $key = Cache::pull("epay.onetouch.key.{$event->deviceId}");
        if ($key === null) return;

        $oneTouch = Epay::oneTouch();
        $code = $oneTouch->getCode($event->deviceId, $key);
        $token = $oneTouch->getToken($event->deviceId, $code->code);

        // Persist $token->token for future payments
    }
}
```

## Laravel Events

| Event | Payload | Triggered when |
|-------|---------|---------------|
| `PaymentReceived` | `NotificationItem $item, string $merchant` | WEB notification with STATUS=PAID |
| `PaymentDenied` | `NotificationItem $item, string $merchant` | WEB notification with STATUS=DENIED |
| `PaymentExpired` | `NotificationItem $item, string $merchant` | WEB notification with STATUS=EXPIRED |
| `BillingObligationChecked` | `InitRequest $request, string $merchant` | Billing `/billing/init` processed |
| `BillingPaymentConfirmed` | `ConfirmRequest $request, string $merchant` | Billing `/billing/confirm` processed |
| `OneTouchAuthorizationCallback` | `string $deviceId, array $params, string $merchant` | One Touch auth redirect (no `id` param) |
| `NoRegPaymentCallback` | `string $paymentId, string $deviceId, array $params, string $merchant` | One Touch noreg redirect (has `id` param) |

## Error Handling

All SDK exceptions extend `Ux2Dev\Epay\Exception\EpayException`:

```php
use Ux2Dev\Epay\Exception\EpayException;
use Ux2Dev\Epay\Exception\ConfigurationException;
use Ux2Dev\Epay\Exception\SigningException;
use Ux2Dev\Epay\Exception\InvalidResponseException;

try {
    $result = $web->handleNotification($_POST);
} catch (InvalidResponseException $e) {
    // CHECKSUM verification failed or invalid data
    // $e->getResponseData() returns the redacted response data
    error_log('Invalid notification: ' . $e->getMessage());
} catch (ConfigurationException $e) {
    // Invalid configuration (empty merchant ID, bad amount format, etc.)
} catch (SigningException $e) {
    // Key loading or signing error
} catch (EpayException $e) {
    // Any other ePay error (e.g. One Touch API error response)
}
```

Sensitive fields (CHECKSUM, ENCODED, SIGNATURE, TOKEN) are automatically redacted in exception data.

## Testing

Run the test suite:

```bash
composer install
vendor/bin/pest
```

## Prerequisites for Production

Before going live with ePay.bg:

1. Sign a contract with ePay.bg
2. Get your KIN (merchant identification number) from your ePay.bg profile
3. Get your secret word from your ePay.bg profile (requires phone verification)
4. For Billing API: register at `mrcs.easypay.bg` and provide your notification URL
5. For RSA signing: generate a key pair and upload the public key to your profile
6. Test everything on `demo.epay.bg` first

## License

MIT
