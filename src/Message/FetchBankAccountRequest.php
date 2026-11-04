<?php

namespace Omnipay\ArifPay\Message;

use Omnipay\Common\Message\ResponseInterface;

/**
 * Look up a bank account before a bankTransfer().
 *
 * The production path really is spelled "acount"; the sandbox's is not.
 *
 * @link https://developer.arifpay.net/docs/wallet-to-bank
 */
class FetchBankAccountRequest extends AbstractRequest
{
    use HasBankAccount;

    public function getData(): array
    {
        $this->validate('apiKey');
        $this->validateBankAccount();

        return [];
    }

    protected function getEndpoint(): string
    {
        $query = '?' . http_build_query([
            'accountNumber' => $this->getAccountNumber(),
            'shortCodes' => $this->getShortCodes(),
        ]);

        return $this->route('/api/checkout/b2b/acount/query' . $query, '/api/sandbox/b2b/account/query' . $query);
    }

    protected function createResponse(array $data, int $httpStatus): ResponseInterface
    {
        return new FetchBankAccountResponse($this, $data, $httpStatus);
    }
}
