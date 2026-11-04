<?php

namespace Omnipay\ArifPay;

/**
 * ArifPay's payment rails and the route each one uses in each environment.
 *
 * Production and sandbox do not differ only by a prefix: M-Pesa has no one-shot
 * sandbox route, Yaya swaps the word order, Binget's sandbox initiate takes a
 * whole checkout body while production's takes a session id. So routes are
 * spelled out rather than derived.
 *
 * `null` means ArifPay publishes no route. For production card and B2C rails
 * that is deliberate - the paths are issued at go-live with an IP whitelist, and
 * the docs say not to hard-code them - so callers pass them as `endpoint`.
 *
 * @link https://developer.arifpay.net/docs
 */
final class Rail
{
    public const TELEBIRR = 'telebirr';
    public const MPESA = 'mpesa';
    public const CBE = 'cbe';
    public const AWASH = 'awash';
    public const BOA = 'boa';
    public const YAYA = 'yaya';
    public const KACHA = 'kacha';
    public const ZAMZAM = 'zamzam';
    public const BINGET = 'binget';
    public const AHADU = 'ahadu';
    public const KAAFI_EBIRR = 'kaafi-ebirr';
    public const COOPAY_EBIRR = 'coopay-ebirr';
    public const WEGAGEN_EBIRR = 'wegagen-ebirr';
    public const NIB_EBIRR = 'nib-ebirr';
    public const AHADU_EBIRR = 'ahadu-ebirr';
    public const PAYPAL = 'paypal';
    public const MASTERCARD = 'mastercard';
    public const VISA = 'visa';
    public const PSS = 'pss';

    /** Create the session and start the payment in one call. */
    public const FLOW_DIRECT = 'direct';
    /** Start the payment on a session that already exists. */
    public const FLOW_SESSION = 'session';
    /** Confirm a payment with the OTP the customer received. */
    public const FLOW_OTP = 'otp';
    /** Send money to a customer (B2C). */
    public const FLOW_PAYOUT = 'payout';

    private const P = '/api/checkout/';
    private const S = '/api/sandbox/c2b/';
    private const B2C = '/api/sandbox/b2c/';

    /**
     * rail => flow => [production path, sandbox path]
     */
    private const ROUTES = [
        self::TELEBIRR => [
            self::FLOW_DIRECT => [self::P . 'telebirr-ussd/transfer/direct', self::S . 'telebirr-ussd/direct/transfer'],
            self::FLOW_SESSION => [null, self::S . 'telebirr-ussd/transfer'],
            self::FLOW_PAYOUT => [null, self::B2C . 'telebirr/transfer'],
        ],
        self::MPESA => [
            self::FLOW_DIRECT => [self::P . 'mpesa/transfer/direct', null],
            self::FLOW_SESSION => [null, self::S . 'mpesa/transfer'],
            self::FLOW_PAYOUT => [null, self::B2C . 'mpesa/transfer'],
        ],
        self::CBE => [
            self::FLOW_DIRECT => [self::P . 'v2/cbe/direct/transfer', self::S . 'cbe/direct/transfer'],
            self::FLOW_SESSION => [self::P . 'cbe/transfer', self::S . 'cbe/transfer'],
            self::FLOW_PAYOUT => [null, self::B2C . 'cbe/transfer'],
        ],
        self::AWASH => [
            self::FLOW_DIRECT => [self::P . 'awash/direct/transfer', self::S . 'awash/direct/transfer'],
            self::FLOW_SESSION => [self::P . 'awash/transfer', self::S . 'awash/transfer'],
        ],
        // In production this path is session-based despite the word "direct".
        self::BOA => [
            self::FLOW_DIRECT => [null, self::S . 'boa/direct/transfer'],
            self::FLOW_SESSION => [self::P . 'boa/direct/transfer', self::S . 'boa/transfer'],
        ],
        self::YAYA => [
            self::FLOW_DIRECT => [self::P . 'yaya/direct/transfer', self::S . 'yaya/transfer/direct'],
            self::FLOW_SESSION => [self::P . 'yaya/transfer', self::S . 'yaya/transfer'],
        ],
        self::KACHA => [
            self::FLOW_DIRECT => [self::P . 'kacha/direct/transfer', self::S . 'kacha/direct/transfer'],
            self::FLOW_SESSION => [self::P . 'kacha/transfer', self::S . 'kacha/transfer'],
        ],
        self::ZAMZAM => [
            self::FLOW_DIRECT => [self::P . 'zamzam/direct/transfer', self::S . 'zamzam/direct/transfer'],
            self::FLOW_SESSION => [self::P . 'zamzam/transfer', self::S . 'zamzam/transfer'],
            self::FLOW_OTP => [self::P . 'zamzam/verify/otp', self::S . 'zamzam/verifyOTP'],
        ],
        self::BINGET => [
            self::FLOW_DIRECT => [null, self::S . 'binget/initiate'],
            self::FLOW_SESSION => [self::P . 'binget/initiate', null],
            self::FLOW_OTP => [self::P . 'binget/verify-otp', self::S . 'binget/verifyOTP'],
        ],
        // Not on the production gateway: ArifPay says to confirm enablement first.
        self::AHADU => [
            self::FLOW_SESSION => [null, self::S . 'ahadu/initiate'],
            self::FLOW_OTP => [null, self::S . 'ahadu/transfer'],
        ],
        self::KAAFI_EBIRR => [
            self::FLOW_DIRECT => [null, self::S . 'kaafi-ebirr/direct/transfer'],
            self::FLOW_SESSION => [self::P . 'kaafi-ebirr/transfer', self::S . 'kaafi-ebirr/transfer'],
        ],
        self::COOPAY_EBIRR => [
            self::FLOW_DIRECT => [null, self::S . 'coopay-ebirr/direct/transfer'],
            self::FLOW_SESSION => [self::P . 'coopay-ebirr/transfer', self::S . 'coopay-ebirr/transfer'],
        ],
        self::WEGAGEN_EBIRR => [
            self::FLOW_DIRECT => [null, self::S . 'wegagen-ebirr/direct/transfer'],
            self::FLOW_SESSION => [self::P . 'wegagen-ebirr/transfer', self::S . 'wegagen-ebirr/transfer'],
        ],
        self::NIB_EBIRR => [
            self::FLOW_DIRECT => [null, self::S . 'nib-ebirr/direct/transfer'],
            self::FLOW_SESSION => [self::P . 'nib-ebirr/transfer', self::S . 'nib-ebirr/transfer'],
        ],
        self::AHADU_EBIRR => [
            self::FLOW_DIRECT => [null, self::S . 'ahadu-ebirr/direct/transfer'],
            self::FLOW_SESSION => [self::P . 'ahadu-ebirr/transfer', self::S . 'ahadu-ebirr/transfer'],
        ],
        self::PAYPAL => [self::FLOW_DIRECT => [null, self::S . 'paypal/direct/transfer']],
        self::MASTERCARD => [self::FLOW_DIRECT => [null, self::S . 'mastercard/direct/transfer']],
        self::VISA => [self::FLOW_DIRECT => [null, self::S . 'visa/direct/transfer']],
        self::PSS => [self::FLOW_DIRECT => [null, self::S . 'pss/direct/transfer']],
    ];

    /** Rails that identify the customer by something other than a required phone. */
    private const PHONE_OPTIONAL = [self::BOA, self::BINGET, self::PAYPAL, self::VISA, self::PSS, self::ZAMZAM];

    /** Rails that charge a bank account, so they need an `accountNumber`. */
    private const ACCOUNT_REQUIRED = [self::ZAMZAM];

    /** Rails whose production success body is an HTML form, not the JSON envelope. */
    private const RETURNS_HTML = [self::BOA, self::VISA, self::PSS];

    public static function exists(string $rail): bool
    {
        return isset(self::ROUTES[$rail]);
    }

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return array_keys(self::ROUTES);
    }

    /**
     * The published path, or null when ArifPay publishes none for this
     * combination of rail, flow and environment.
     */
    public static function path(string $rail, string $flow, bool $testMode): ?string
    {
        return self::ROUTES[$rail][$flow][$testMode ? 1 : 0] ?? null;
    }

    public static function requiresPhone(string $rail): bool
    {
        return !in_array($rail, self::PHONE_OPTIONAL, true);
    }

    public static function requiresAccountNumber(string $rail): bool
    {
        return in_array($rail, self::ACCOUNT_REQUIRED, true);
    }

    public static function returnsHtml(string $rail): bool
    {
        return in_array($rail, self::RETURNS_HTML, true);
    }
}
