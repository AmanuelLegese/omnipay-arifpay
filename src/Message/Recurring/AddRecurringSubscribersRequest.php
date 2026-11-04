<?php

namespace Omnipay\ArifPay\Message\Recurring;

/**
 * Attach `phoneNumbers` to a plan; each gets a payment link by SMS.
 *
 * Numbers are validated here since ArifPay accepts anything, even "bad", and would
 * then fail to reach that subscriber. The call can partly succeed: check
 * successfullyAdded, alreadySubscribed and failedPhoneNumbers in getData().
 */
class AddRecurringSubscribersRequest extends AbstractRecurringRequest
{
    public function getPhoneNumbers(): ?array
    {
        return $this->getParameter('phoneNumbers');
    }

    /**
     * @return $this
     */
    public function setPhoneNumbers(?array $value): self
    {
        return $this->setParameter('phoneNumbers', $value);
    }

    public function getData(): array
    {
        $this->validate('apiKey', 'transactionReference', 'phoneNumbers');

        return [
            'phoneNumbers' => array_map(
                fn ($phone): string => $this->normalisePhone((string) $phone),
                array_values($this->getPhoneNumbers())
            ),
        ];
    }

    protected function getEndpoint(): string
    {
        return $this->recurringPath('/recurring-payment-session/' . $this->reference() . '/subscribers');
    }
}
