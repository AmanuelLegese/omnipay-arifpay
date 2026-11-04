<?php

namespace Omnipay\ArifPay\Message\Recurring;

/**
 * List your plans a page at a time. Takes `page` (from 0), `size`, and the
 * optional filters `frequency`, `status` and `startDate`.
 */
class FetchRecurringPlansRequest extends AbstractRecurringRequest
{
    use Paginates;

    public function getFrequency(): ?string
    {
        return $this->getParameter('frequency');
    }

    /**
     * @return $this
     */
    public function setFrequency(?string $value): self
    {
        return $this->setParameter('frequency', $value);
    }

    public function getStatus(): ?string
    {
        return $this->getParameter('status');
    }

    /**
     * @return $this
     */
    public function setStatus(?string $value): self
    {
        return $this->setParameter('status', $value);
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

    protected function getHttpMethod(): string
    {
        return 'GET';
    }

    protected function getEndpoint(): string
    {
        $startDate = $this->getStartDate();

        return $this->recurringPath('/recurring-sessions' . $this->pageQuery([
            'startDate' => $startDate === null ? null : $this->formatDate($startDate),
            'frequency' => $this->getFrequency(),
            'status' => $this->getStatus(),
        ]));
    }
}
