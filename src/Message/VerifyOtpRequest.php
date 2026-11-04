<?php

namespace Omnipay\ArifPay\Message;

use Omnipay\ArifPay\Rail;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Confirm a Binget, Zamzam or Ahadu payment with the customer's OTP. The sandbox
 * OTP is always 123456, but simMode SUCCESS overrides the check and accepts any
 * code. A wrong code reports getCode() "00" in production.
 */
class VerifyOtpRequest extends AbstractRequest
{
    use RoutesByRail;

    public function getOtp(): ?string
    {
        return $this->getParameter('otp');
    }

    /**
     * @return $this
     */
    public function setOtp(?string $value): self
    {
        return $this->setParameter('otp', $value);
    }

    public function getAccountNumber(): ?string
    {
        return $this->getParameter('accountNumber');
    }

    /**
     * Required for Zamzam and Ahadu; the same value given when the payment began.
     *
     * @return $this
     */
    public function setAccountNumber(?string $value): self
    {
        return $this->setParameter('accountNumber', $value);
    }

    public function getData(): array
    {
        $this->validate('apiKey', 'transactionReference', 'otp');
        $rail = $this->requireRail();

        if ($rail === Rail::ZAMZAM || $rail === Rail::AHADU) {
            $this->validate('accountNumber');
        }

        return array_filter([
            'sessionId' => $this->getTransactionReference(),
            'otp' => $this->getOtp(),
            'accountNumber' => $this->getAccountNumber(),
        ], static fn ($value): bool => $value !== null && $value !== '');
    }

    protected function getEndpoint(): string
    {
        return $this->railPath(Rail::FLOW_OTP);
    }

    protected function createResponse(array $data, int $httpStatus): ResponseInterface
    {
        return new TransactionResponse($this, $data, $httpStatus);
    }
}
