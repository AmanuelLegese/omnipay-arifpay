<?php

namespace Omnipay\ArifPay\Message\Recurring;

/**
 * One subscriber's plan, next payment date and payment history.
 */
class FetchRecurringReportRequest extends AbstractRecurringRequest
{
    protected function getHttpMethod(): string
    {
        return 'GET';
    }

    public function getData(): array
    {
        $this->validate('apiKey', 'transactionReference', 'phone');

        return [];
    }

    protected function getEndpoint(): string
    {
        return $this->recurringPath('/report/' . $this->reference() . '/' . $this->phoneSegment());
    }
}
