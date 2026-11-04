<?php

namespace Omnipay\ArifPay\Message;

use Omnipay\Common\Message\RedirectResponseInterface;

/**
 * Response to creating a checkout session.
 *
 * On success this is always a redirect to the hosted page and never a completed
 * payment, so isSuccessful() stays false. Keep getTransactionReference() against
 * your order: a second session for one order can charge the customer twice.
 */
class PurchaseResponse extends AbstractResponse implements RedirectResponseInterface
{
    public function isSuccessful(): bool
    {
        return false;
    }

    public function isRedirect(): bool
    {
        return !$this->isError() && $this->getRedirectUrl() !== null;
    }

    public function getRedirectUrl(): ?string
    {
        return self::scalar($this->getPayload(), 'paymentUrl');
    }

    public function getRedirectMethod(): string
    {
        return 'GET';
    }

    public function getRedirectData(): array
    {
        return [];
    }

    public function getTransactionReference(): ?string
    {
        return self::scalar($this->getPayload(), 'sessionId');
    }

    /**
     * Your nonce. ArifPay's reply doesn't repeat it, so this is what was sent.
     */
    public function getTransactionId(): ?string
    {
        return $this->getRequest()->getTransactionId();
    }

    /**
     * The total ArifPay computed from the items.
     */
    public function getAmount(): ?string
    {
        return self::money(self::scalar($this->getPayload(), 'totalAmount'));
    }
}
