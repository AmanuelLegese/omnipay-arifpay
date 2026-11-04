<?php

namespace Omnipay\ArifPay\Message\Recurring;

/**
 * Read your retry settings for failed and overdue charges.
 */
class FetchRecurringSettingsRequest extends AbstractRecurringRequest
{
    protected function getHttpMethod(): string
    {
        return 'GET';
    }

    protected function getEndpoint(): string
    {
        return $this->recurringPath('/settings');
    }
}
