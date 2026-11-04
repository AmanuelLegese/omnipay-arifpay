<?php

namespace Omnipay\ArifPay\Message;

use Omnipay\ArifPay\Rail;

/**
 * Send money to a customer's wallet (B2C) from a session purchase() created.
 *
 * Telebirr, CBE and M-Pesa only. The production path is issued at go-live with
 * your IP whitelist, so `endpoint` is required there and `secretKey` must be set.
 * A 200 only means the provider accepted it: isPending() is true until the
 * webhook or a lookup says otherwise. Payouts cannot be refunded.
 *
 * @link https://developer.arifpay.net/docs/b2c/telebirr
 */
class PayoutRequest extends SessionPaymentRequest
{
    public function getData(): array
    {
        if (!$this->getTestMode()) {
            $this->validate('secretKey');
        }

        return parent::getData();
    }

    protected function getHeaders(): array
    {
        return parent::getHeaders() + array_filter(['x-arifpay-secret-key' => $this->getSecretKey()]);
    }

    protected function getEndpoint(): string
    {
        return $this->railPath(Rail::FLOW_PAYOUT);
    }
}
