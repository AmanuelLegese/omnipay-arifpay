<?php

namespace Omnipay\ArifPay\Message;

use Omnipay\ArifPay\Rail;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Capture a PayPal order after the customer returns from approvalUrl.
 *
 * ArifPay publishes no capture path for either environment: the production one
 * is issued at go-live, so `endpoint` is required. Not tested against the API.
 *
 * @link https://developer.arifpay.net/docs/c2b/paypal
 */
class CaptureRequest extends AbstractRequest
{
    use RoutesByRail;

    public function getOrderId(): ?string
    {
        return $this->getParameter('orderId');
    }

    /**
     * @return $this
     */
    public function setOrderId(?string $value): self
    {
        return $this->setParameter('orderId', $value);
    }

    public function getData(): array
    {
        $this->validate('apiKey', 'transactionReference', 'orderId', 'endpoint');

        return [
            'sessionId' => $this->getTransactionReference(),
            'orderId' => $this->getOrderId(),
        ];
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
