<?php

namespace Omnipay\ArifPay\Message;

use Omnipay\ArifPay\Rail;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Create a session and start the payment on one rail in a single call, with no
 * hosted page. A USSD prompt or OTP then reaches the customer's phone.
 *
 * Takes everything purchase() takes, plus `rail`. Not every rail has this route
 * in production; the error says so, and sessionPayment() is the other way in.
 *
 * @link https://developer.arifpay.net/docs/c2b/telebirr
 */
class DirectPurchaseRequest extends PurchaseRequest
{
    use RoutesByRail;

    public function getAccountNumber(): ?string
    {
        return $this->getParameter('accountNumber');
    }

    /**
     * The account to charge, on bank rails such as Zamzam.
     *
     * @return $this
     */
    public function setAccountNumber(?string $value): self
    {
        return $this->setParameter('accountNumber', $value);
    }

    public function getData(): array
    {
        $rail = $this->requireRail();
        $data = parent::getData();

        $phone = $this->requirePhoneFor($rail);

        if ($phone !== null) {
            $data['phone'] = $phone;
        }

        $accountNumber = $this->getAccountNumber();

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
        return $this->railPath(Rail::FLOW_DIRECT);
    }

    protected function createResponse(array $data, int $httpStatus): ResponseInterface
    {
        return new TransactionResponse($this, $data, $httpStatus);
    }
}
