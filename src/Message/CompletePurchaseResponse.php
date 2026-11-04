<?php

namespace Omnipay\ArifPay\Message;

/**
 * A paid session only counts as successful if it was for the amount you expected,
 * when you passed one. A mismatch means the session isn't the one this order
 * thinks it is, so it needs a person to look at it rather than fulfilment.
 */
class CompletePurchaseResponse extends TransactionResponse
{
    public function isSuccessful(): bool
    {
        return parent::isSuccessful() && !$this->isAmountMismatch();
    }

    public function isAmountMismatch(): bool
    {
        $expected = $this->getRequest()->getAmount();

        return $expected !== null && $this->getAmount() !== null && $expected !== $this->getAmount();
    }

    public function getMessage(): ?string
    {
        if ($this->isAmountMismatch()) {
            return sprintf(
                'The session total %s does not match the expected amount %s',
                $this->getAmount(),
                $this->getRequest()->getAmount()
            );
        }

        return parent::getMessage();
    }
}
