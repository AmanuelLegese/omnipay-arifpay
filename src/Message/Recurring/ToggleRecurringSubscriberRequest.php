<?php

namespace Omnipay\ArifPay\Message\Recurring;

/**
 * Flip a subscriber between active and cancelled. Going back to active sends no
 * new link; use reinitiateRecurringSubscriber() for that.
 *
 * The docs say a PENDING subscriber can be cancelled too, but the sandbox answers
 * 500 "Unexpected subscriber status: PENDING".
 */
class ToggleRecurringSubscriberRequest extends AbstractRecurringRequest
{
    public function getData(): array
    {
        $this->validate('apiKey', 'transactionReference', 'phone');

        return [];
    }

    protected function getEndpoint(): string
    {
        return $this->recurringPath('/' . $this->reference() . '/subscriber/' . $this->phoneSegment() . '/status');
    }
}
