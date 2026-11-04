<?php

namespace Omnipay\ArifPay\Message;

use Omnipay\Common\Message\ResponseInterface;

/**
 * List every transaction attempt on a session, with receipts and timestamps.
 *
 * Use this for reconciliation, not to decide whether an order is paid. ArifPay
 * documents that API-key checks do not apply to /api/verify/**, so unlike
 * fetchTransaction() it can answer for a session that is not yours. Never pass it
 * an id that came from a webhook or a browser. There is no sandbox twin.
 *
 * @link https://developer.arifpay.net/docs/verify/session-id
 */
class VerifyTransactionRequest extends AbstractRequest
{
    protected function getHttpMethod(): string
    {
        return 'GET';
    }

    public function getData(): array
    {
        $this->validate('apiKey', 'transactionReference');

        return [];
    }

    protected function getEndpoint(): string
    {
        return $this->route(
            '/api/verify/transaction?uuid=' . $this->segment((string) $this->getTransactionReference()),
            null
        );
    }

    protected function createResponse(array $data, int $httpStatus): ResponseInterface
    {
        return new VerifyTransactionResponse($this, $data, $httpStatus);
    }
}
