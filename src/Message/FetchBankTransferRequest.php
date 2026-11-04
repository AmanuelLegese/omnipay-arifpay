<?php

namespace Omnipay\ArifPay\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Check a wallet-to-bank transfer by `transactionId`, your nonce.
 *
 * Only the sandbox route is published. It is the only way to check a transfer
 * besides the webhook, so if ArifPay issues you a production path, pass it as
 * `endpoint`. The response is only known from the sandbox.
 */
class FetchBankTransferRequest extends AbstractRequest
{
    public function getEndpointOverride(): ?string
    {
        return $this->getParameter('endpoint');
    }

    /**
     * A path issued by ArifPay, with a leading slash. The nonce is added to it.
     *
     * @return $this
     */
    public function setEndpoint(?string $value): self
    {
        return $this->setParameter('endpoint', $value);
    }

    public function getData(): array
    {
        $this->validate('apiKey', 'transactionId');

        return [];
    }

    protected function getEndpoint(): string
    {
        $query = '?nonce=' . rawurlencode((string) $this->getTransactionId());
        $override = $this->getEndpointOverride();

        if ($override !== null && $override !== '') {
            if (!str_starts_with($override, '/')) {
                throw new InvalidRequestException('The endpoint parameter must be a path starting with /');
            }

            return $override . $query;
        }

        if (!$this->getTestMode()) {
            throw new InvalidRequestException(
                'ArifPay publishes no production route for this. '
                . 'If you were issued one, pass it as the endpoint parameter'
            );
        }

        return '/api/sandbox/b2b/status/query' . $query;
    }

    protected function createResponse(array $data, int $httpStatus): ResponseInterface
    {
        return new FetchBankTransferResponse($this, $data, $httpStatus);
    }
}
