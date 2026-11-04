<?php

namespace Omnipay\ArifPay\Message;

use Omnipay\Common\Exception\InvalidRequestException;

/**
 * The destination of a wallet-to-bank transfer.
 */
trait HasBankAccount
{
    public function getAccountNumber(): ?string
    {
        return $this->getParameter('accountNumber');
    }

    /**
     * @return $this
     */
    public function setAccountNumber(?string $value): self
    {
        return $this->setParameter('accountNumber', $value);
    }

    public function getShortCodes(): ?string
    {
        return $this->getParameter('shortCodes');
    }

    /**
     * The bank's name code: CBE, AWB, BOA, DASHEN, ABAY, WEGAGEN, ZAMZAM, LION,
     * BUNNA, HIBRET, ZEMEN, ADIB, BERHAN, TSEHAY, AMHARA, ENAT or COOP.
     *
     * @return $this
     */
    public function setShortCodes(?string $value): self
    {
        return $this->setParameter('shortCodes', $value);
    }

    /**
     * @throws InvalidRequestException
     */
    protected function validateBankAccount(): void
    {
        $this->validate('accountNumber', 'shortCodes');

        if (ctype_digit((string) $this->getShortCodes())) {
            throw new InvalidRequestException(
                'The shortCodes parameter must be a bank name such as CBE, not a numeric clearing code'
            );
        }
    }
}
