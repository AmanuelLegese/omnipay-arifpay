# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-11-04

First release. Requires PHP 8.2+ and `omnipay/common` ^3.5.

Checked against ArifPay's sandbox. Nothing has been exercised with a production key.

### Added

- Hosted checkout: `purchase()`, `completePurchase()`, `fetchTransaction()` by session
  id or nonce, `fetchTransactionByReceipt()`, `verifyTransaction()` and `void()`.
- Direct rails: `directPurchase()`, `sessionPayment()` and `verifyOtp()` across Telebirr,
  M-Pesa, CBE, Awash, BOA, Yaya, Kacha, Zamzam, Binget, Ahadu, the five Ebirr channels,
  PayPal, Mastercard, Visa and PSS. `capture()` for PayPal. Routes ArifPay issues at
  go-live are passed as `endpoint`.
- `acceptNotification()`. ArifPay's webhooks are unsigned, so the status and amount come
  from an authenticated session lookup rather than the body, and an event that ArifPay
  does not confirm throws.
- Payouts: `payout()` (B2C), `fetchBankAccount()`, `bankTransfer()` and
  `fetchBankTransfer()`.
- Sub-account attribution through `splitToAccount`.
- Recurring payments: plans, subscribers, settings, reports and scheduled processing.
- Phone numbers are validated and converted to ArifPay's 251 format.
- Unsupported methods such as `refund()` throw `BadMethodCallException`, as Omnipay specifies.
- `completePurchase()` takes the same parameters as `purchase()`, and a paid session for a
  different `amount` is not successful (`isAmountMismatch()`).

[Unreleased]: https://github.com/AmanuelLegese/omnipay-arifpay/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/AmanuelLegese/omnipay-arifpay/releases/tag/v0.1.0
