<?php

namespace Omnipay\ArifPay\Message;

use Omnipay\Common\Message\ResponseInterface;

/**
 * Look up a session from a provider receipt number and the rail it was paid on,
 * such as a receipt a customer quotes to support. There is no sandbox twin.
 *
 * @link https://developer.arifpay.net/docs/verify/transaction-id
 */
class FetchTransactionByReceiptRequest extends AbstractRequest
{
    public function getReceipt(): ?string
    {
        return $this->getParameter('receipt');
    }

    /**
     * @return $this
     */
    public function setReceipt(?string $value): self
    {
        return $this->setParameter('receipt', $value);
    }

    public function getPaymentType(): ?string
    {
        return $this->getParameter('paymentType');
    }

    /**
     * ArifPay's payment type enum, e.g. TELEBIRR_USSD or BOA.
     *
     * @return $this
     */
    public function setPaymentType(?string $value): self
    {
        return $this->setParameter('paymentType', $value);
    }

    public function getData(): array
    {
        $this->validate('apiKey', 'receipt', 'paymentType');

        return [
            'transactionId' => $this->getReceipt(),
            'paymentType' => $this->getPaymentType(),
        ];
    }

    protected function getEndpoint(): string
    {
        return $this->route('/api/checkout/getSessionByTransactionId', null);
    }

    protected function createResponse(array $data, int $httpStatus): ResponseInterface
    {
        return new TransactionResponse($this, $data, $httpStatus);
    }
}
