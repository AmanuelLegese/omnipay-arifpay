<?php

namespace Omnipay\ArifPay\Message;

/**
 * A raw JSON list of attempts, outside the usual envelope. An unknown session is
 * an empty list on HTTP 200 rather than a 404, so it reads as not successful.
 */
class VerifyTransactionResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        if ($this->isError()) {
            return false;
        }

        foreach ($this->getTransactions() as $transaction) {
            if (($transaction['status'] ?? null) === TransactionResponse::STATUS_SUCCESS) {
                return true;
            }
        }

        return false;
    }

    public function getTransactions(): array
    {
        return array_is_list($this->data)
            ? array_values(array_filter($this->data, 'is_array'))
            : [];
    }
}
