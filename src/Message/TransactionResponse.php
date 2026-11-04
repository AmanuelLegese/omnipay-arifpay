<?php

namespace Omnipay\ArifPay\Message;

use Omnipay\Common\Message\RedirectResponseInterface;

/**
 * A reply describing a transaction: a session lookup or a payment attempt.
 *
 * isSuccessful() is true only for a clean reply with status SUCCESS. Telebirr,
 * M-Pesa and CBE are asynchronous, so a production payment normally comes back
 * pending and "USSD request sent" is not "paid". The sandbox settles instantly.
 *
 * The opposite also happens: retrying a paid session is error:true with a
 * transaction whose status is SUCCESS. That attempt failed, so it does not count
 * here; fetchTransaction() is how to learn the session is in fact paid.
 */
class TransactionResponse extends AbstractResponse implements RedirectResponseInterface
{
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_EXPIRED = 'EXPIRED';

    public function isSuccessful(): bool
    {
        return !$this->isError() && $this->getStatus() === self::STATUS_SUCCESS;
    }

    /**
     * Accepted and still in progress. A reply with no status at all, such as a
     * wallet-to-bank accept or a new PayPal order, is waiting on a later step too.
     */
    public function isPending(): bool
    {
        $status = $this->getStatus();

        return !$this->isError() && ($status === self::STATUS_PENDING || $status === null);
    }

    public function isFailed(): bool
    {
        return $this->getStatus() === self::STATUS_FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->getStatus() === self::STATUS_CANCELLED;
    }

    public function isExpired(): bool
    {
        return $this->getStatus() === self::STATUS_EXPIRED;
    }

    /**
     * The transaction status, upper-cased. The docs spell cancelled both ways
     * and the API sends CANCELLED, so the other spelling is folded into it.
     */
    public function getStatus(): ?string
    {
        $status = self::scalar($this->getTransaction(), 'transactionStatus')
            ?? self::scalar($this->getPayload(), 'transactionStatus');

        if ($status === null) {
            return null;
        }

        $status = strtoupper($status);

        return $status === 'CANCELED' ? self::STATUS_CANCELLED : $status;
    }

    /**
     * ArifPay's session id: what fetchTransaction() and void() take.
     *
     * The nested uuid goes first on purpose. On Mastercard, data.sessionId is
     * Mastercard's hosted checkout id rather than ArifPay's.
     */
    public function getTransactionReference(): ?string
    {
        return self::scalar($this->getSession(), 'uuid')
            ?? self::scalar($this->getPayload(), 'uuid', 'SessionId', 'sessionId');
    }

    /**
     * Your nonce.
     */
    public function getTransactionId(): ?string
    {
        return self::scalar($this->getSession(), 'nonce') ?? self::scalar($this->getPayload(), 'nonce');
    }

    /**
     * The receipt number from the provider, once there is one.
     */
    public function getReceipt(): ?string
    {
        return self::scalar($this->getTransaction(), 'transactionId');
    }

    /**
     * The session total. transaction.amount is deliberately not used: it stays
     * 0.0 until the payment has settled.
     */
    public function getAmount(): ?string
    {
        return self::money(
            self::scalar($this->getSession(), 'totalAmount')
                ?? self::scalar($this->getPayload(), 'totalAmount', 'amount')
        );
    }

    public function getFee(): ?string
    {
        return self::money(self::scalar($this->getTransaction(), 'fee'));
    }

    /**
     * The rail paid with, e.g. TELEBIRR_USSD. fetchTransactionByReceipt() needs it.
     */
    public function getPaymentType(): ?string
    {
        return self::scalar($this->getTransaction(), 'paymentType')
            ?? self::scalar($this->getPayload(), 'paymentType');
    }

    public function getPhone(): ?string
    {
        return self::scalar($this->getSession(), 'phone') ?? self::scalar($this->getPayload(), 'phone');
    }

    /**
     * The `url` of a transfer reply. This is not a payment redirect: where it is
     * set in the sandbox it is simply your own successUrl or errorUrl, even on
     * payments that are still pending. Hence it is not used by isRedirect().
     */
    public function getUrl(): ?string
    {
        return self::scalar($this->getPayload(), 'url');
    }

    /**
     * Mastercard's hosted checkout session id, for use with Checkout.js.
     */
    public function getHostedSessionId(): ?string
    {
        return self::scalar($this->getPayload(), 'sessionId');
    }

    /**
     * The PayPal order id, which capture() needs.
     */
    public function getOrderId(): ?string
    {
        return self::scalar($this->getPayload(), 'orderId');
    }

    /**
     * The HTML form BOA and PSS answer with, to send to the customer's browser.
     */
    public function getHtml(): ?string
    {
        return is_array($this->data) ? self::scalar($this->data, 'body') : null;
    }

    public function isRedirect(): bool
    {
        return !$this->isError() && $this->getRedirectUrl() !== null;
    }

    public function getRedirectUrl(): ?string
    {
        return self::scalar($this->getPayload(), 'approvalUrl');
    }

    public function getRedirectMethod(): string
    {
        return 'GET';
    }

    public function getRedirectData(): array
    {
        return [];
    }

    private function getTransaction(): array
    {
        $transaction = $this->getPayload()['transaction'] ?? null;

        return is_array($transaction) ? $transaction : [];
    }

    /**
     * Transfer replies nest the session under the transaction; lookups don't.
     */
    private function getSession(): array
    {
        $session = $this->getTransaction()['checkoutSession'] ?? null;

        return is_array($session) ? $session : $this->getPayload();
    }
}
