<?php

namespace Omnipay\ArifPay\Message\Recurring;

use Omnipay\ArifPay\Message\AbstractRequest;
use Omnipay\ArifPay\Message\Response;
use Omnipay\Common\Message\ResponseInterface;

/**
 * The recurring API, whose path ArifPay spells "recuring". These routes reply
 * with bare objects rather than the usual envelope, and some with plain text.
 *
 * `transactionReference` holds the plan's uuid throughout.
 *
 * @link https://developer.arifpay.net/docs/features/recurring
 */
abstract class AbstractRecurringRequest extends AbstractRequest
{
    public function getPhone(): ?string
    {
        return $this->getParameter('phone');
    }

    /**
     * @return $this
     */
    public function setPhone(?string $value): self
    {
        return $this->setParameter('phone', $value);
    }

    public function getData(): array
    {
        $this->validate('apiKey');

        return [];
    }

    protected function acceptsNonJson(): bool
    {
        return true;
    }

    protected function recurringPath(string $path): string
    {
        return ($this->getTestMode() ? '/api/sandbox/recuring' : '/api/recuring') . $path;
    }

    protected function reference(): string
    {
        return $this->segment((string) $this->getTransactionReference());
    }

    protected function phoneSegment(): string
    {
        return $this->segment($this->normalisePhone((string) $this->getPhone()));
    }

    protected function createResponse(array $data, int $httpStatus): ResponseInterface
    {
        return new Response($this, $data, $httpStatus);
    }
}
