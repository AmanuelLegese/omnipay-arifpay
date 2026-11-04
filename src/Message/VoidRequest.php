<?php

namespace Omnipay\ArifPay\Message;

use Omnipay\Common\Message\ResponseInterface;

/**
 * Cancel an unpaid session by `transactionReference`.
 *
 * ArifPay has no refund API, so this cannot reverse a payment that has been made.
 * Cancelling a session twice is not an error.
 */
class VoidRequest extends AbstractRequest
{
    public function getData(): array
    {
        $this->validate('apiKey', 'transactionReference');

        return [];
    }

    protected function getEndpoint(): string
    {
        $path = $this->segment((string) $this->getTransactionReference());

        return $this->route('/api/checkout/session/cancel/' . $path, '/api/sandbox/checkout/session/cancel/' . $path);
    }

    protected function createResponse(array $data, int $httpStatus): ResponseInterface
    {
        return new VoidResponse($this, $data, $httpStatus);
    }
}
