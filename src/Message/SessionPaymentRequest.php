<?php

namespace Omnipay\ArifPay\Message;

use Omnipay\ArifPay\Rail;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Start a payment on a session purchase() already created, on one rail.
 *
 * On Binget and Ahadu this sends the OTP, which verifyOtp() then confirms.
 * BOA answers with an HTML form rather than JSON: see getHtml() on the response.
 */
class SessionPaymentRequest extends AbstractRequest
{
    use RoutesByRail;

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

    public function getAccountNumber(): ?string
    {
        return $this->getParameter('accountNumber');
    }

    /**
     * @return $this
     */
    public function setAccountNumber(?string $value): self
    {
        return $this->setParameter('accountNumber', $value);
    }

    public function getData(): array
    {
        $this->validate('apiKey', 'transactionReference');
        $rail = $this->requireRail();

        $data = ['sessionId' => $this->getTransactionReference()];

        $phone = $this->requirePhoneFor($rail);

        if ($phone !== null) {
            $data['phoneNumber'] = $phone;
        }

        // Ahadu's account number is the customer's phone, and it validates both.
        $accountNumber = $this->getAccountNumber() ?: ($rail === Rail::AHADU ? $phone : null);

        if ($accountNumber !== null && $accountNumber !== '') {
            $data['accountNumber'] = $accountNumber;
        } elseif (Rail::requiresAccountNumber($rail)) {
            throw new InvalidRequestException(sprintf('The accountNumber parameter is required for %s', $rail));
        }

        return $data;
    }

    protected function acceptsNonJson(): bool
    {
        return Rail::returnsHtml((string) $this->getRail());
    }

    protected function getEndpoint(): string
    {
        return $this->railPath(Rail::FLOW_SESSION);
    }

    protected function createResponse(array $data, int $httpStatus): ResponseInterface
    {
        return new TransactionResponse($this, $data, $httpStatus);
    }
}
