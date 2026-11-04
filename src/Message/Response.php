<?php

namespace Omnipay\ArifPay\Message;

/**
 * A reply that is only data - recurring plans, subscribers, settings. Successful
 * means the call worked; read the result with getData().
 */
class Response extends AbstractResponse
{
    public function isSuccessful(): bool
    {
        return !$this->isError();
    }

    /**
     * A recurring plan's server-generated uuid.
     */
    public function getTransactionReference(): ?string
    {
        return is_array($this->data) ? self::scalar($this->data, 'uuid') : null;
    }

    /**
     * Plain-text replies, as the recurring process routes give.
     */
    public function getBody(): ?string
    {
        return is_array($this->data) ? self::scalar($this->data, 'body') : null;
    }
}
