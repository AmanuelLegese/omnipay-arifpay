# Omnipay: ArifPay

**ArifPay driver for the Omnipay PHP payment processing library**

[![Unit Tests](https://github.com/AmanuelLegese/omnipay-arifpay/actions/workflows/run-tests.yml/badge.svg)](https://github.com/AmanuelLegese/omnipay-arifpay/actions/workflows/run-tests.yml)

[Omnipay](https://github.com/thephpleague/omnipay) is a framework agnostic, multi-gateway
payment processing library for PHP. This package implements [ArifPay](https://arifpay.net)
support: hosted checkout, direct wallet, bank and card rails, OTP flows, webhooks, payouts,
wallet to bank, sub-account splits and recurring payments.

## Status

Pre-1.0: minor versions may still change the API. See [CHANGELOG.md](CHANGELOG.md).

**Nothing has been run with a production key.** Everything was built from ArifPay's docs and
Postman collections, then checked against its sandbox, so treat your first live integration as
unverified. See [What has been tested](#what-has-been-tested).

## Installation

```
composer require league/omnipay:^3 amanuellegese/omnipay-arifpay
```

`league/omnipay` brings in Omnipay with Guzzle as its HTTP client. To use a different client,
require `omnipay/common` and any `php-http/client-implementation` instead.

Requires PHP 8.2+ and `omnipay/common` ^3.5.

## Setup

```php
use Omnipay\Omnipay;

$gateway = Omnipay::create('ArifPay');
$gateway->setApiKey(getenv('ARIFPAY_API_KEY'));
```

The package never reads `.env` or any configuration itself. Your app supplies the key from wherever
it keeps its settings, such as a `.env` file, its config, or a database. Keep real keys out of your code.

`testMode` switches to ArifPay's sandbox, which has its own host and routes. ArifPay's public
sandbox key is `arif-sandbox-portal-key`.

```php
$gateway->setTestMode(true);
$gateway->setApiKey('arif-sandbox-portal-key');
$gateway->setSimMode('FAIL'); // optional: force the simulated outcome
```

The production host defaults to `https://gateway.arifpay.net`, which ArifPay gives as an
example. Confirm yours at go-live and set it with `$gateway->setBaseUrl(...)`.

## Hosted checkout

```php
$response = $gateway->purchase([
    'transactionId' => $order->nonce,          // your unique nonce
    'amount' => '100.00',                      // or 'items' => [...]
    'description' => 'Order 1234',
    'returnUrl' => 'https://shop.example/success',
    'cancelUrl' => 'https://shop.example/cancel',
    'errorUrl' => 'https://shop.example/error',
    'notifyUrl' => 'https://shop.example/arifpay/webhook',
    'phone' => '0912345678',                   // optional here; converted to 2519...
])->send();

if ($response->isRedirect()) {
    $order->update(['arifpay_session' => $response->getTransactionReference()]);
    $response->redirect();
} elseif ($response->isDuplicateNonce()) {
    // This nonce already has a session. Don't retry with a new nonce: see below.
} else {
    echo $response->getMessage();
}
```

The customer returning to your `returnUrl` proves nothing, so ask ArifPay. ArifPay documents no
parameters on its redirects, so `completePurchase()` needs the reference you stored:

```php
$response = $gateway->completePurchase($samePurchaseParameters + [
    'transactionReference' => $order->arifpay_session,
])->send();

if ($response->isSuccessful()) {
    // Paid, and for the amount you passed.
} elseif ($response->isAmountMismatch()) {
    // Paid, but not for this order's amount. Don't fulfil: have someone look at it.
} elseif ($response->isPending()) {
    // Wallets are asynchronous. Wait for the webhook, or check again later.
}
```

As Omnipay recommends, pass the same parameters as `purchase()`. The `amount` is what gets checked,
so without one a paid session is successful whatever its total.

**One session per order.** Two sessions, which means two nonces, can charge the customer twice.
Refuse a second session until the first is failed, expired or cancelled.

`void()` cancels an unpaid session. ArifPay has no refund API, so nothing can reverse a payment
made through this package. `refund()`, `authorize()` and the card token methods throw
`BadMethodCallException`, as Omnipay specifies, and `supportsRefund()` returns false.

## Webhooks

**ArifPay does not sign its webhooks**, so anyone who finds your `notifyUrl` can post a
`SUCCESS`. `acceptNotification()` therefore looks the session up with your API key and reports
ArifPay's answer, not the body's. That makes it one HTTP call, unlike most Omnipay gateways. It refuses a session that isn't yours or doesn't match the
event's nonce.

```php
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Common\Message\NotificationInterface;

try {
    $notification = $gateway->acceptNotification();
} catch (InvalidRequestException | InvalidResponseException $e) {
    http_response_code(400); // not a real event, or not one ArifPay confirms
    return;
}

$order = Order::findBySession($notification->getTransactionReference());

if ($notification->getTransactionStatus() === NotificationInterface::STATUS_COMPLETED
    && $notification->getAmount() === $order->amount) {
    $order->fulfil(); // make this idempotent: deliveries can repeat
}

http_response_code(200);
```

- **ArifPay never retries a failed delivery.** Poll `fetchTransaction()` for orders still pending.
- A webhook can arrive just before the session updates, in which case it reads as pending. Poll
  those orders too.
- A network failure during the lookup throws Omnipay's `NetworkException`. Let that fail the
  request, and pick the order up when polling.
- Wallet-to-bank events aren't checkout sessions, so pass `['verify' => false]` for them. All
  values are then unauthenticated claims and `isVerified()` is false: confirm them yourself
  before acting.

## Direct payments

Skip the hosted page and start a payment on one rail. A USSD prompt or OTP reaches the
customer's phone, so a production reply is normally pending, not paid.

```php
use Omnipay\ArifPay\Rail;

// Create the session and start paying in one call
$response = $gateway->directPurchase([
    'rail' => Rail::TELEBIRR,
    'phone' => '0912345678',
    // ...and the same parameters as purchase()
])->send();

// Or pay a session you already created
$response = $gateway->sessionPayment([
    'rail' => Rail::CBE,
    'transactionReference' => $sessionId,
    'phone' => '0912345678',
])->send();
```

| Rail | `directPurchase` (production) | `sessionPayment` (production) | Notes |
|---|---|---|---|
| `TELEBIRR` | yes | sandbox only | |
| `MPESA` | yes | sandbox only | Phone must start 2517 |
| `CBE` | yes | yes | |
| `AWASH`, `YAYA`, `KACHA` | yes | yes | |
| `ZAMZAM` | yes | yes | Needs `accountNumber`, then `verifyOtp()` |
| `BOA` | sandbox only | yes | Replies with HTML: see `getHtml()` |
| `BINGET` | sandbox only | yes | Then `verifyOtp()` |
| `KAAFI_EBIRR`, `COOPAY_EBIRR`, `WEGAGEN_EBIRR`, `NIB_EBIRR`, `AHADU_EBIRR` | sandbox only | yes | |
| `AHADU` | sandbox only, as a session payment | not published | Then `verifyOtp()`; confirm enablement with ArifPay |
| `PAYPAL`, `MASTERCARD`, `VISA`, `PSS` | issued at go-live | | See below |

**Routes issued at go-live.** ArifPay keeps its production card and B2C paths out of its public
docs and asks that they not be hard-coded, so this package doesn't. Pass yours as `endpoint`:

```php
$response = $gateway->directPurchase([
    'rail' => Rail::PAYPAL,
    'endpoint' => '/path/issued/by/arifpay',
    // ...
])->send();

$response->redirect(); // to PayPal's approval page

// When the customer comes back
$gateway->capture([
    'transactionReference' => $sessionId,
    'orderId' => $orderId,
    'endpoint' => '/capture/path/issued/by/arifpay',
])->send();
```

On Mastercard, `getHostedSessionId()` gives the id for Checkout.js, while
`getTransactionReference()` stays ArifPay's own. BOA and PSS reply with a form to send to the
browser: `echo $response->getHtml();`.

**OTP rails:**

```php
$gateway->sessionPayment(['rail' => Rail::BINGET, 'transactionReference' => $sessionId])->send();

$response = $gateway->verifyOtp([
    'rail' => Rail::BINGET,
    'transactionReference' => $sessionId,
    'otp' => $otpFromCustomer,
])->send();
```

## Looking up transactions

| Method | By | Use for |
|---|---|---|
| `fetchTransaction()` | `transactionReference` or `transactionId` (nonce) | Deciding whether an order is paid |
| `fetchTransactionByReceipt()` | `receipt` and `paymentType` | A receipt a customer quotes |
| `verifyTransaction()` | `transactionReference` | Reconciliation only |

Don't use `verifyTransaction()` to decide an order is paid, or pass it an id from a webhook or
browser. ArifPay documents that API-key checks don't apply to that route, so it can answer for
sessions that aren't yours.

## Payouts

**To a wallet (B2C):** Telebirr, CBE and M-Pesa only. Create a session first. In production this
needs `secretKey`, a whitelisted server IP, and the `endpoint` issued at go-live.

```php
$gateway->setSecretKey('your-withdrawal-secret');

$response = $gateway->payout([
    'rail' => Rail::TELEBIRR,
    'transactionReference' => $sessionId,
    'phone' => '0912345678',
    'endpoint' => '/path/issued/by/arifpay',
])->send();
```

**To a bank account:** the production host is issued on request, so pass it as `baseUrl`.

```php
$account = $gateway->fetchBankAccount([
    'accountNumber' => '1000123456789',
    'shortCodes' => 'CBE',
])->send();

if ($account->isSuccessful()) {
    $response = $gateway->bankTransfer([
        'transactionId' => $payout->nonce,
        'amount' => '500.00',
        'accountNumber' => '1000123456789',
        'shortCodes' => 'CBE',
        // ...and the URLs purchase() needs
    ])->send();
}
```

Both are only accepted at this point: `isPending()` is true until the webhook arrives, and
"Transfer successful" does not mean settled. In the sandbox, also call
`$gateway->setTransferKey('sandbox-w2b-key')`. `fetchBankTransfer()` checks a transfer, but
ArifPay only publishes that route for the sandbox.

## Sub-accounts

Pass `splitToAccount` to `purchase()` with the email of a sub-account registered in your dashboard.

## Recurring payments

```php
$plan = $gateway->createRecurringPlan([
    'amount' => '200.00',
    'frequency' => 'MONTHLY',
    'startDate' => '2027-01-01T09:00:00',
    'name' => 'Monthly donation',
    'returnUrl' => '...', 'cancelUrl' => '...', 'errorUrl' => '...', 'notifyUrl' => '...',
])->send();

$gateway->addRecurringSubscribers([
    'transactionReference' => $plan->getTransactionReference(),
    'phoneNumbers' => ['0911661336'],
])->send();
```

The results are in `getData()`. Also available: `fetchRecurringPlans`, `updateRecurringPlan`,
`fetchRecurringSubscribers`, `reinitiateRecurringSubscriber`, `toggleRecurringSubscriber`,
`fetchRecurringReport`, `fetchRecurringSettings`, `updateRecurringSettings`,
`processRecurringPending` and `processRecurringOverdue`. The last two are for scheduled jobs, and
`processRecurringOverdue` sends real SMS messages despite being a GET.

## Errors

Check the response rather than the HTTP status: some failures come back as HTTP 200.

| Accessor | Gives |
|---|---|
| `isError()` | Whether the API call failed |
| `getMessage()` | ArifPay's message, with any field errors flattened into it |
| `getCode()` | `"03"` amount over the maximum, `"00"` invalid or expired OTP, else null |
| `getHttpStatus()` | The HTTP status |
| `isDuplicateNonce()` | The nonce already has a session |

`isSuccessful()` on a payment needs a clean reply and status `SUCCESS`, so retrying a paid session
(`error: true` with a `SUCCESS` transaction) is not successful. Use `fetchTransaction()` to find
out that session is in fact paid. The default session maximum is 43,200 ETB.

## What has been tested

| Against | Coverage |
|---|---|
| ArifPay's sandbox (`make test-sandbox`) | Checkout, void, every rail except capture, OTP, B2C, wallet to bank, most recurring routes, and a forged webhook being refused |
| Mocked responses only (`make test`) | The production routes, BOA's and PSS's HTML replies, `fetchTransaction` by nonce, `fetchTransactionByReceipt`, `verifyTransaction`, `toggleRecurringSubscriber`, `updateRecurringSettings`, `processRecurringOverdue` |
| Nothing | `capture()`: ArifPay publishes no route or full reply for it in either environment |

Where the sandbox differs from the docs, this package follows what the sandbox did:

- The sandbox settles direct payments instantly, while production is asynchronous. Code that only
  works in the sandbox may be skipping the pending state, so test with both in mind.
- `simMode` `SUCCESS` overrides the OTP check, so any code passes. Leave it unset to test a wrong OTP.
- The docs say a `PENDING` subscriber can be cancelled, but the sandbox answers 500.
- An amount over the maximum is documented as a 400 with code `"03"`, but the sandbox answers a
  500 with no code. `getCode()` would be null there.
- ArifPay's MCP tooling mentions HMAC-signed webhooks. Sandbox deliveries carry no signature, and
  no such header is documented. If ArifPay confirms production signs them, verifying that would be
  a welcome addition to the lookup.

## Not covered

ArifPay's E-Invoice API is a separate ERP product with its own host and login. POS flows,
ArifBot and the MCP server have no merchant API to wrap.

## Development

PHP and Composer run in Docker, so nothing is needed on the host:

```
make install
make test           # mocked, offline
make test-sandbox   # against ArifPay's sandbox, over the network
make style
```

The sandbox occasionally answers 503 `failure to get a peer from the ring-balancer`, which is
worth one retry before assuming a test has really failed.

## Support

For issues with this package, use the
[GitHub issue tracker](https://github.com/AmanuelLegese/omnipay-arifpay/issues). For your ArifPay
account, contact ArifPay on 9395 or info@arifpay.com.
