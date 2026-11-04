<?php

namespace Omnipay\ArifPay\Message;

use Omnipay\Common\Message\ResponseInterface;

/**
 * Pay out from your wallet to a bank account (wallet to bank).
 *
 * Takes purchase()'s parameters plus `accountNumber` and `shortCodes`. Check the
 * account with fetchBankAccount() first. "Transfer successful" only means it was
 * accepted, so isPending() is true until the webhook arrives. The production host
 * is only issued on request, with an IP whitelist: pass it as `baseUrl`.
 *
 * @link https://developer.arifpay.net/docs/wallet-to-bank
 */
class BankTransferRequest extends PurchaseRequest
{
    use HasBankAccount;

    public function getData(): array
    {
        $this->validateBankAccount();

        $data = parent::getData() + [
            'accountNumber' => $this->getAccountNumber(),
            'shortCodes' => $this->getShortCodes(),
        ];

        // The sandbox reads a root amount; production documents items only.
        if ($this->getTestMode()) {
            $data['amount'] = array_sum(array_map(
                static fn (array $item): float => $item['price'] * $item['quantity'],
                $data['items']
            ));
        }

        return $data;
    }

    protected function getHeaders(): array
    {
        return parent::getHeaders() + array_filter([
            'x-arifpay-secret-key' => $this->getSecretKey(),
            'x-arifpay-transfer-key' => $this->getTransferKey(),
        ]);
    }

    protected function getEndpoint(): string
    {
        return $this->route('/api/wallet-tobank/transfer', '/api/sandbox/b2b/transfer');
    }

    protected function createResponse(array $data, int $httpStatus): ResponseInterface
    {
        return new TransactionResponse($this, $data, $httpStatus);
    }
}
