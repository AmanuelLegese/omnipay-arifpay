<?php

namespace Omnipay\ArifPay\Message\Recurring;

use Omnipay\Common\Exception\InvalidRequestException;

/**
 * Create a plan. The uuid ArifPay generates comes back as getTransactionReference().
 */
class CreateRecurringPlanRequest extends AbstractRecurringRequest
{
    private const FREQUENCIES = ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'];

    public function getErrorUrl(): ?string
    {
        return $this->getParameter('errorUrl');
    }

    /**
     * @return $this
     */
    public function setErrorUrl(?string $value): self
    {
        return $this->setParameter('errorUrl', $value);
    }

    public function getFrequency(): ?string
    {
        return $this->getParameter('frequency');
    }

    /**
     * DAILY, WEEKLY, MONTHLY or YEARLY.
     *
     * @return $this
     */
    public function setFrequency(?string $value): self
    {
        return $this->setParameter('frequency', $value);
    }

    public function getStartDate(): \DateTimeInterface|string|null
    {
        return $this->getParameter('startDate');
    }

    /**
     * @return $this
     */
    public function setStartDate(\DateTimeInterface|string|null $value): self
    {
        return $this->setParameter('startDate', $value);
    }

    public function getName(): ?string
    {
        return $this->getParameter('name');
    }

    /**
     * The plan's name, shown to subscribers in their SMS.
     *
     * @return $this
     */
    public function setName(?string $value): self
    {
        return $this->setParameter('name', $value);
    }

    public function getStatus(): ?string
    {
        return $this->getParameter('status');
    }

    /**
     * ArifPay defaults this to ACTIVE.
     *
     * @return $this
     */
    public function setStatus(?string $value): self
    {
        return $this->setParameter('status', $value);
    }

    public function getData(): array
    {
        $this->validate(
            'apiKey',
            'amount',
            'frequency',
            'startDate',
            'name',
            'returnUrl',
            'cancelUrl',
            'errorUrl',
            'notifyUrl'
        );

        if (!in_array($this->getFrequency(), self::FREQUENCIES, true)) {
            throw new InvalidRequestException(
                'The frequency parameter must be one of ' . implode(', ', self::FREQUENCIES)
            );
        }

        return array_filter([
            'amount' => (float) $this->getAmount(),
            'frequency' => $this->getFrequency(),
            'startDate' => $this->formatDate($this->getStartDate()),
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
        return $this->recurringPath('/recurring-payment-session');
    }
}
