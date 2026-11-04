<?php

namespace Omnipay\ArifPay\Message;

/**
 * Successful only if the session really ended up cancelled, so a call that
 * returns a session already paid doesn't read as a successful void.
 */
class VoidResponse extends TransactionResponse
{
    public function isSuccessful(): bool
    {
        return !$this->isError() && $this->isCancelled();
    }
}
