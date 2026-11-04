<?php

namespace Omnipay\ArifPay\Message;

use Omnipay\Common\Message\AbstractResponse as OmnipayAbstractResponse;
use Omnipay\Common\Message\RequestInterface;

/**
 * Shared reading of ArifPay's replies, which come in several shapes:
 *
 *     {"error": bool, "msg": ..., "data": null|"03"|{...}}   the usual envelope
 *     {"timestamp": ..., "status": 404, "error": "Not Found", "message": ...}   Spring
 *     {"message": "...", "status": "error"}                  recurring errors
 *     {"message": "failure to get a peer from the ring-balancer"}   the proxy
 *     {"content": [...]} or a bare object or list            recurring and verify
 */
abstract class AbstractResponse extends OmnipayAbstractResponse
{
    public function __construct(RequestInterface $request, $data, private int $httpStatus = 200)
    {
        parent::__construct($request, $data);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * Whether the API call itself failed. HTTP stays 200 on some failures - a
     * retry on a paid session is 200 with error:true - so the code alone is not
     * enough. Spring's `error` is a string, hence the strict comparison.
     */
    public function isError(): bool
    {
        return ($this->data['error'] ?? null) === true
            || ($this->data['status'] ?? null) === 'error'
            || $this->httpStatus >= 400;
    }

    /**
     * Flattens validation errors, which arrive as a map under `data`, so that
     * callers always get a string saying what was wrong.
     */
    public function getMessage(): ?string
    {
        $message = null;

        foreach (['msg', 'message'] as $key) {
            if (is_string($this->data[$key] ?? null) && $this->data[$key] !== '') {
                $message = $this->data[$key];
                break;
            }
        }

        $details = $this->data['data'] ?? null;

        if ($this->isError() && is_array($details) && !isset($details['transaction'])) {
            $parts = [];
            foreach ($details as $field => $error) {
                if (is_scalar($error)) {
                    $parts[] = $field . ': ' . $error;
                }
            }

            if ($parts !== []) {
                return ($message !== null ? $message . ': ' : '') . implode(', ', $parts);
            }
        }

        return $message;
    }

    /**
     * ArifPay's short string error code: "03" for an amount over the maximum and
     * "00" for an invalid or expired OTP. Most errors have none.
     */
    public function getCode(): ?string
    {
        $code = $this->data['data'] ?? null;

        return $this->isError() && is_string($code) && $code !== '' ? $code : null;
    }

    /**
     * The nonce was already used. Nothing new was created, so look the existing
     * session up rather than retrying with a new nonce, which can charge twice.
     */
    public function isDuplicateNonce(): bool
    {
        return $this->isError() && $this->getMessage() === 'session already exist';
    }

    /**
     * The `data` object, or an empty array when there is none.
     */
    protected function getPayload(): array
    {
        $data = $this->data['data'] ?? null;

        return is_array($data) ? $data : [];
    }

    protected static function scalar(array $source, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $source[$key] ?? null;

            if (is_scalar($value) && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * Money comes back as a JSON float, so 100.0 would read as "100". Match the
     * two decimals of Omnipay's getAmount() to make comparing them safe.
     */
    protected static function money(?string $value): ?string
    {
        return $value === null || !is_numeric($value) ? null : number_format((float) $value, 2, '.', '');
    }
}
