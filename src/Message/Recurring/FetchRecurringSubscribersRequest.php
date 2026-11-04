<?php

namespace Omnipay\ArifPay\Message\Recurring;

/**
 * List a plan's subscribers a page at a time.
 */
class FetchRecurringSubscribersRequest extends AbstractRecurringRequest
{
    use Paginates;

    protected function getHttpMethod(): string
    {
        return 'GET';
    }

    public function getData(): array
    {
        $this->validate('apiKey', 'transactionReference');

        return [];
    }

    protected function getEndpoint(): string
    {
        return $this->recurringPath('/recurring-sessions/' . $this->reference() . '/subscribers' . $this->pageQuery());
    }
}
