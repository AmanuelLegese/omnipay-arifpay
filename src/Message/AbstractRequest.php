<?php

namespace Omnipay\ArifPay\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\AbstractRequest as OmnipayAbstractRequest;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Shared behaviour for every ArifPay request: authentication, environment
 * selection, JSON transport, and building a response from the HTTP reply.
 */
abstract class AbstractRequest extends OmnipayAbstractRequest
{
    /**
     * ArifPay gives this as an example and asks merchants to confirm their own
     * host at go-live, which is what setBaseUrl() is for.
     */
    public const PRODUCTION_URL = 'https://gateway.arifpay.net';

    public const SANDBOX_URL = 'https://gateway.arifpay.org';

    /**
     * Path appended to the base URL, with a leading slash.
     */
    abstract protected function getEndpoint(): string;

    abstract protected function createResponse(array $data, int $httpStatus): ResponseInterface;

    protected function getHttpMethod(): string
    {
        return 'POST';
    }

    /**
     * Whether a 2xx reply may legitimately be something other than JSON. BOA and
     * PSS answer with an HTML form, and the recurring process routes with text.
     */
    protected function acceptsNonJson(): bool
    {
        return false;
    }

    public function getApiKey(): ?string
    {
        return $this->getParameter('apiKey');
    }

    /**
     * @return $this
     */
    public function setApiKey(?string $value): self
    {
        return $this->setParameter('apiKey', $value);
    }

    public function getSecretKey(): ?string
    {
        return $this->getParameter('secretKey');
    }

    /**
     * The withdrawal secret, sent as x-arifpay-secret-key. Only payouts need it.
     *
     * @return $this
     */
    public function setSecretKey(?string $value): self
    {
        return $this->setParameter('secretKey', $value);
    }

    public function getTransferKey(): ?string
    {
        return $this->getParameter('transferKey');
    }

    /**
     * Sent as x-arifpay-transfer-key. The sandbox rejects wallet-to-bank
     * transfers without it; production does not document it.
     *
     * @return $this
     */
    public function setTransferKey(?string $value): self
    {
        return $this->setParameter('transferKey', $value);
    }

    public function getBaseUrl(): string
    {
        $configured = $this->getParameter('baseUrl');

        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/');
        }

        return $this->getTestMode() ? self::SANDBOX_URL : self::PRODUCTION_URL;
    }

    /**
     * The gateway host issued for your merchant. Wallet to bank's production host
     * is only given out on request, so it can also be set per request.
     *
     * @return $this
     */
    public function setBaseUrl(?string $value): self
    {
        return $this->setParameter('baseUrl', $value);
    }

    public function getSimMode(): ?string
    {
        return $this->getParameter('simMode');
    }

    /**
     * Sandbox only: `SUCCESS` or `FAIL`, forcing the simulated outcome.
     *
     * @return $this
     */
    public function setSimMode(?string $value): self
    {
        return $this->setParameter('simMode', $value);
    }

    public function getFeeSource(): ?string
    {
        return $this->getParameter('feeSource');
    }

    /**
     * Sandbox only: `CUSTOMER` or `MERCHANT`. In production the fee source comes
     * from your merchant profile and cannot be chosen per request.
     *
     * @return $this
     */
    public function setFeeSource(?string $value): self
    {
        return $this->setParameter('feeSource', $value);
    }

    /**
     * Omnipay's validate() only rejects null, but the gateway's default parameters
     * are empty strings. Without this a key that was never configured would pass
     * and go out as a blank header, failing later with a far less clear error.
     *
     * @throws InvalidRequestException
     */
    public function validate(...$args): void
    {
        parent::validate(...$args);

        foreach ($args as $key) {
            if ($this->getParameter($key) === '') {
                throw new InvalidRequestException("The $key parameter is required");
            }
        }
    }

    protected function getHeaders(): array
    {
        $headers = [
            'x-arifpay-key' => (string) $this->getApiKey(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($this->getTestMode()) {
            $headers += array_filter([
                'x-arifpay-sim-mode' => $this->getSimMode(),
                'x-arifpay-fee-source' => $this->getFeeSource(),
            ]);
        }

        return $headers;
    }

    /**
     * @param array $data
     */
    public function sendData($data): ResponseInterface
    {
        $httpResponse = $this->httpClient->request(
            $this->getHttpMethod(),
            $this->getBaseUrl() . $this->getEndpoint(),
            $this->getHeaders(),
            $data === [] ? null : json_encode($data)
        );

        $status = $httpResponse->getStatusCode();
        $body = (string) $httpResponse->getBody();
        $payload = json_decode($body, true, 512, JSON_BIGINT_AS_STRING);

        if (!is_array($payload)) {
            // An outage or a proxy can answer with HTML or an empty body. Decoding
            // that yields null, which would otherwise surface as a silent failure
            // with no message, so synthesise an envelope instead.
            $payload = $this->acceptsNonJson() && $status >= 200 && $status < 300
                ? ['error' => false, 'msg' => null, 'data' => null, 'body' => $body]
                : [
                    'error' => true,
                    'msg' => sprintf('Invalid response from ArifPay (HTTP %d)', $status),
                    'data' => null,
                ];
        }

        return $this->response = $this->createResponse($payload, $status);
    }

    /**
     * Picks the route for the current environment.
     *
     * A few documented routes have no sandbox twin and answer it with a bare
     * Spring 404. Saying so here is kinder than reporting "No message available".
     *
     * @throws InvalidRequestException
     */
    protected function route(string $production, ?string $sandbox): string
    {
        if (!$this->getTestMode()) {
            return $production;
        }

        if ($sandbox === null) {
            throw new InvalidRequestException(sprintf('%s has no ArifPay sandbox route', $production));
        }

        return $sandbox;
    }

    /**
     * Values land in URL paths, and some come from webhooks or browsers. Encoding
     * stops a crafted value from reaching a different route with our API key.
     */
    protected function segment(string $value): string
    {
        return rawurlencode($value);
    }

    /**
     * ArifPay wants 251XXXXXXXXX, and rejects or ignores +251 and 09 forms. The
     * local forms are unambiguous, so accept and convert them, and refuse the
     * rest here: recurring subscribers will happily accept "bad" as a phone.
     *
     * @throws InvalidRequestException
     */
    protected function normalisePhone(string $phone): string
    {
        $digits = preg_replace('/[\s()+-]/', '', $phone);

        if (preg_match('/^0([79]\d{8})$/', $digits, $match)) {
            $digits = '251' . $match[1];
        }

        if (!preg_match('/^251[79]\d{8}$/', $digits)) {
            throw new InvalidRequestException(sprintf(
                'The phone "%s" is not a valid Ethiopian mobile number (expected 251XXXXXXXXX)',
                $phone
            ));
        }

        return $digits;
    }

    /**
     * ArifPay's timestamps are ISO-8601 without an offset.
     */
    protected function formatDate(\DateTimeInterface|string $date): string
    {
        return is_string($date) ? $date : $date->format('Y-m-d\TH:i:s');
    }
}
