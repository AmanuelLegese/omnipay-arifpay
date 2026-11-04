<?php

namespace Omnipay\ArifPay\Message;

/**
 * Successful means the account exists: the bank's responseCode must be "0", and
 * ArifPay nests that two levels down inside a successful envelope.
 */
class FetchBankAccountResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return !$this->isError() && self::scalar($this->getValue(), 'responseCode') === '0';
    }

    public function getAccountName(): ?string
    {
        return self::scalar($this->getAccountInfo(), 'fullNames');
    }

    public function getAccountNumber(): ?string
    {
        return self::scalar($this->getAccountInfo(), 'accountNumber');
    }

    public function getResponseDescription(): ?string
    {
        return self::scalar($this->getValue(), 'responseDescription');
    }

    private function getValue(): array
    {
        $value = $this->getPayload()['value'] ?? null;

        return is_array($value) ? $value : [];
    }

    private function getAccountInfo(): array
    {
        $info = $this->getValue()['bankAccountInfo'] ?? null;

        return is_array($info) ? $info : [];
    }
}
