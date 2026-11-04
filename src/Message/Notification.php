<?php

namespace Omnipay\ArifPay\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Common\Message\NotificationInterface;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

/**
 * An incoming ArifPay webhook.
 *
 * ## Why the status is not read from the webhook
 *
 * ArifPay does not sign its webhooks. Neither the docs nor a captured sandbox
 * delivery show a signature header, so anyone who learns your notifyUrl can post
 * `"transactionStatus": "SUCCESS"`. (ArifPay's MCP tool emits HMAC-signed test
 * webhooks, but those are synthetic and are not what the gateway sends.)
 *
 * So Gateway::acceptNotification() looks the session up with your API key, and
 * the status and amount here are ArifPay's answer. A forged event then has no
 * effect beyond one lookup, and a session belonging to someone else isn't found.
 * What the webhook itself said stays available as getClaimedStatus().
 *
 * A webhook can beat the session update, in which case the status reads pending.
 * ArifPay never retries deliveries, so keep polling fetchTransaction() on those.
 *
 * @link https://developer.arifpay.net/docs/webhook-shape
 */
class Notification implements NotificationInterface
{
    private array $data;

    /**
     * @param TransactionResponse|null $verified the session lookup for this
     *        event, or null for an unverified notification
     * @throws InvalidRequestException when the body is not an ArifPay event
     * @throws InvalidResponseException when ArifPay doesn't confirm the event
     */
    public function __construct(HttpRequest $httpRequest, private ?TransactionResponse $verified = null)
    {
        $decoded = json_decode($httpRequest->getContent(), true, 512, JSON_BIGINT_AS_STRING);
        $this->data = is_array($decoded) ? $decoded : [];

        if ($verified !== null) {
            $this->assertConfirmedBy($verified);
        }
    }

    /**
     * The session id named in a webhook body, before any verification.
     *
     * @throws InvalidRequestException
     */
    public static function sessionIdFrom(HttpRequest $httpRequest): string
    {
        $decoded = json_decode($httpRequest->getContent(), true, 512, JSON_BIGINT_AS_STRING);
        $sessionId = is_array($decoded) ? ($decoded['sessionId'] ?? $decoded['uuid'] ?? null) : null;

        if (!is_string($sessionId) || $sessionId === '') {
            throw new InvalidRequestException('The ArifPay webhook has no sessionId');
        }

        return $sessionId;
    }

    /**
     * False only when acceptNotification() was told not to verify, in which case
     * every value here is an unauthenticated claim.
     */
    public function isVerified(): bool
    {
        return $this->verified !== null;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getTransactionReference(): ?string
    {
        return $this->value('sessionId') ?? $this->value('uuid');
    }

    /**
     * Your nonce: the session's own when verified, whatever the webhook omitted.
     */
    public function getTransactionId(): ?string
    {
        return $this->verified !== null ? $this->verified->getTransactionId() : $this->value('nonce');
    }

    /**
     * Omnipay's three-state status. Anything unrecognised is failed rather than
     * assumed benign, so a new ArifPay status can't read as a completed payment.
     *
     * @return NotificationInterface::STATUS_*
     */
    public function getTransactionStatus(): string
    {
        return match ($this->getStatus()) {
            TransactionResponse::STATUS_SUCCESS => self::STATUS_COMPLETED,
            TransactionResponse::STATUS_PENDING => self::STATUS_PENDING,
            default => self::STATUS_FAILED,
        };
    }

    /**
     * ArifPay's own status: from the lookup if verified, otherwise the claim.
     */
    public function getStatus(): ?string
    {
        return $this->verified !== null ? $this->verified->getStatus() : $this->getClaimedStatus();
    }

    /**
     * What the webhook body said, which proves nothing about the payment.
     */
    public function getClaimedStatus(): ?string
    {
        $status = $this->value('transactionStatus');

        return $status === null ? null : strtoupper($status);
    }

    /**
     * The session total, for comparing against your order.
     */
    public function getAmount(): ?string
    {
        if ($this->verified !== null) {
            return $this->verified->getAmount();
        }

        $amount = $this->value('totalAmount');

        return is_numeric($amount) ? number_format((float) $amount, 2, '.', '') : null;
    }

    /**
     * The rail, e.g. TELEBIRR_USSD. Wallet-to-bank events say MPESA_BANK.
     */
    public function getPaymentMethod(): ?string
    {
        return $this->value('paymentMethod');
    }

    public function getPhone(): ?string
    {
        return $this->value('phone');
    }

    /**
     * The provider's receipt number.
     */
    public function getReceipt(): ?string
    {
        if ($this->verified !== null) {
            return $this->verified->getReceipt();
        }

        $receipt = $this->data['transaction']['transactionId'] ?? null;

        return is_scalar($receipt) && $receipt !== '' ? (string) $receipt : null;
    }

    public function getMessage(): ?string
    {
        return $this->getStatus();
    }

    /**
     * @throws InvalidResponseException
     */
    private function assertConfirmedBy(TransactionResponse $response): void
    {
        if ($response->isError()) {
            throw new InvalidResponseException(sprintf(
                'ArifPay did not confirm webhook session %s: %s',
                $this->getTransactionReference(),
                $response->getMessage()
            ));
        }

        // The lookup was made with an id from an unauthenticated body, so make
        // sure it found that session and that the event's nonce belongs to it.
        // Otherwise a real paid session's id could be paired with another order's
        // nonce, marking that other order as paid.
        if ($response->getTransactionReference() !== $this->getTransactionReference()) {
            throw new InvalidResponseException('The ArifPay session does not match the webhook');
        }

        $claimedNonce = $this->value('nonce');

        if ($claimedNonce !== null && $response->getTransactionId() !== $claimedNonce) {
            throw new InvalidResponseException('The ArifPay session nonce does not match the webhook');
        }
    }

    private function value(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return is_scalar($value) && $value !== '' ? (string) $value : null;
    }
}
