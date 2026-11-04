<?php

namespace Omnipay\ArifPay\Message;

class FetchBankTransferResponse extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        $value = $this->getValue();

        return !$this->isError()
            && ($value['success'] ?? null) === true
            && self::scalar($value, 'responseCode') === '0';
    }

    /**
     * The provider's description of the outcome, e.g. "Completed".
     */
    public function getResponseDescription(): ?string
    {
        return self::scalar($this->getValue(), 'responseDescription', 'errorMessage');
    }

    private function getValue(): array
    {
        $value = $this->getPayload()['value'] ?? null;

        return is_array($value) ? $value : [];
    }
}
