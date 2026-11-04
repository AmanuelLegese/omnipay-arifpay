<?php

namespace Omnipay\ArifPay\Message\Recurring;

/**
 * Send a cancelled subscriber a new payment link and set them back to PENDING.
 * ArifPay refuses this for a subscriber who is not cancelled.
 */
class ReinitiateRecurringSubscriberRequest extends AbstractRecurringRequest
{
    public function getData(): array
    {
        $this->validate('apiKey', 'transactionReference', 'phone');

        return [];
    }

    protected function getEndpoint(): string
    {
        return $this->recurringPath(
            '/recurring-payment-session/' . $this->reference() . '/reinitiate/subscriber/' . $this->phoneSegment()
        );
    }
}
