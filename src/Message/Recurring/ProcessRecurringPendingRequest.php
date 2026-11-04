<?php

namespace Omnipay\ArifPay\Message\Recurring;

/**
 * Sync PENDING subscribers with their checkout status; meant for a scheduled job.
 * After 24 hours without a payment, ArifPay cancels the subscriber. Pass a
 * `transactionReference` to limit it to one plan. The result is plain text: see
 * getBody() on the response.
 */
class ProcessRecurringPendingRequest extends AbstractRecurringRequest
{
    protected function getHttpMethod(): string
    {
        return 'GET';
    }

    protected function getEndpoint(): string
    {
        $plan = $this->getTransactionReference() ? '/' . $this->reference() : '';

        return $this->recurringPath('/process/pending/payments' . $plan);
    }
}
