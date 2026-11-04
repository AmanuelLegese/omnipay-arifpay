<?php

namespace Omnipay\ArifPay\Message\Recurring;

/**
 * Bill active subscribers whose next payment date has passed. Meant for a
 * scheduled job, and despite being a GET it sends subscribers real SMS messages.
 */
class ProcessRecurringOverdueRequest extends AbstractRecurringRequest
{
    protected function getHttpMethod(): string
    {
        return 'GET';
    }

    protected function getEndpoint(): string
    {
        return $this->recurringPath('/process/outdated/payments');
    }
}
