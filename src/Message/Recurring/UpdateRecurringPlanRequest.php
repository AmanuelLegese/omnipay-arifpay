<?php

namespace Omnipay\ArifPay\Message\Recurring;

/**
 * Change a plan. Only the parameters you pass are sent.
 */
class UpdateRecurringPlanRequest extends CreateRecurringPlanRequest
{
    protected function getHttpMethod(): string
    {
        return 'PUT';
    }

    public function getData(): array
    {
        $this->validate('apiKey', 'transactionReference');
        $startDate = $this->getStartDate();

        return array_filter([
            'amount' => $this->getParameter('amount') === null ? null : (float) $this->getAmount(),
            'frequency' => $this->getFrequency(),
            'startDate' => $startDate === null ? null : $this->formatDate($startDate),
            'recurringPaymentName' => $this->getName(),
            'successUrl' => $this->getReturnUrl(),
            'cancelUrl' => $this->getCancelUrl(),
            'errorUrl' => $this->getErrorUrl(),
            'notifyUrl' => $this->getNotifyUrl(),
            'status' => $this->getStatus(),
        ], static fn ($value): bool => $value !== null);
    }

    protected function getEndpoint(): string
    {
        return $this->recurringPath('/update/recurring/' . $this->reference());
    }
}
