<?php

namespace Omnipay\ArifPay\Message\Recurring;

/**
 * Set your retry behaviour: `maxRetryCount` (0-100), `retryIntervalHours`
 * (1-8760) and `retryStartAfterPayments` (0-1000).
 */
class UpdateRecurringSettingsRequest extends AbstractRecurringRequest
{
    public function getMaxRetryCount(): ?int
    {
        return $this->getParameter('maxRetryCount');
    }

    /**
     * @return $this
     */
    public function setMaxRetryCount(?int $value): self
    {
        return $this->setParameter('maxRetryCount', $value);
    }

    public function getRetryIntervalHours(): ?int
    {
        return $this->getParameter('retryIntervalHours');
    }

    /**
     * @return $this
     */
    public function setRetryIntervalHours(?int $value): self
    {
        return $this->setParameter('retryIntervalHours', $value);
    }

    public function getRetryStartAfterPayments(): ?int
    {
        return $this->getParameter('retryStartAfterPayments');
    }

    /**
     * @return $this
     */
    public function setRetryStartAfterPayments(?int $value): self
    {
        return $this->setParameter('retryStartAfterPayments', $value);
    }

    protected function getHttpMethod(): string
    {
        return 'PUT';
    }

    public function getData(): array
    {
        $this->validate('apiKey', 'maxRetryCount', 'retryIntervalHours', 'retryStartAfterPayments');

        return [
            'maxRetryCount' => $this->getMaxRetryCount(),
            'retryIntervalHours' => $this->getRetryIntervalHours(),
            'retryStartAfterPayments' => $this->getRetryStartAfterPayments(),
        ];
    }

    protected function getEndpoint(): string
    {
        return $this->recurringPath('/settings');
    }
}
