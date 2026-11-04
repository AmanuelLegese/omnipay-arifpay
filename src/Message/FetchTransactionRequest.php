<?php

namespace Omnipay\ArifPay\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Look up a checkout session by `transactionReference` (session id) or
 * `transactionId` (your nonce). The reference wins if both are given.
 *
 * This is the lookup to trust: it is authenticated, so it only finds sessions
 * belonging to your API key. Only the session id route has a sandbox twin.
 *
 * @link https://developer.arifpay.net/docs/get-checkout-session
 * @link https://developer.arifpay.net/docs/verify/nonce
 */
class FetchTransactionRequest extends AbstractRequest
{
    protected function getHttpMethod(): string
    {
        return 'GET';
    }

    public function getData(): array
    {
        $this->validate('apiKey');

        if (!$this->getTransactionReference() && !$this->getTransactionId()) {
            throw new InvalidRequestException('The transactionReference or transactionId parameter is required');
        }

        return [];
    }

    protected function getEndpoint(): string
    {
        $reference = $this->getTransactionReference();

        if ($reference) {
            $path = $this->segment((string) $reference);

            return $this->route('/api/checkout/session/' . $path, '/api/sandbox/checkout/session/' . $path);
        }

        if ($this->getTestMode()) {
            throw new InvalidRequestException(
                'Looking a session up by nonce has no ArifPay sandbox route: pass the transactionReference'
            );
        }

        $nonce = $this->segment((string) $this->getTransactionId());

        return $this->route('/api/checkout/getSessionByNonce/' . $nonce, null);
    }

    protected function createResponse(array $data, int $httpStatus): ResponseInterface
    {
        return new TransactionResponse($this, $data, $httpStatus);
    }
}
