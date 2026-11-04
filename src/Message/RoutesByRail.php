<?php

namespace Omnipay\ArifPay\Message;

use Omnipay\ArifPay\Rail;
use Omnipay\Common\Exception\InvalidRequestException;

/**
 * For requests whose route depends on the rail. An explicit `endpoint` wins,
 * which is how the production card and B2C paths issued at go-live get used.
 */
trait RoutesByRail
{
    public function getRail(): ?string
    {
        return $this->getParameter('rail');
    }

    /**
     * One of the Rail constants.
     *
     * @return $this
     */
    public function setRail(?string $value): self
    {
        return $this->setParameter('rail', $value);
    }

    public function getEndpointOverride(): ?string
    {
        return $this->getParameter('endpoint');
    }

    /**
     * A path issued by ArifPay, with a leading slash, used instead of the
     * published route. It is a path only, so the API key cannot be sent to
     * another host by mistake; use `baseUrl` to change the host.
     *
     * @return $this
     */
    public function setEndpoint(?string $value): self
    {
        return $this->setParameter('endpoint', $value);
    }

    /**
     * @throws InvalidRequestException
     */
    protected function requireRail(): string
    {
        $this->validate('rail');
        $rail = (string) $this->getRail();

        if (!Rail::exists($rail)) {
            throw new InvalidRequestException(sprintf(
                'Unknown rail "%s". Use one of: %s',
                $rail,
                implode(', ', Rail::all())
            ));
        }

        return $rail;
    }

    /**
     * @throws InvalidRequestException
     */
    protected function railPath(string $flow): string
    {
        $override = $this->getEndpointOverride();

        if ($override !== null && $override !== '') {
            if (!str_starts_with($override, '/')) {
                throw new InvalidRequestException('The endpoint parameter must be a path starting with /');
            }

            return $override;
        }

        $rail = $this->requireRail();
        $path = Rail::path($rail, $flow, (bool) $this->getTestMode());

        if ($path === null) {
            throw new InvalidRequestException(sprintf(
                'ArifPay publishes no %s %s route for the rail "%s". '
                . 'If you were issued one at go-live, pass it as the endpoint parameter',
                $this->getTestMode() ? 'sandbox' : 'production',
                $flow,
                $rail
            ));
        }

        return $path;
    }

    protected function requirePhoneFor(string $rail): ?string
    {
        $phone = $this->getPhone();

        if ($phone === null || $phone === '') {
            if (Rail::requiresPhone($rail)) {
                throw new InvalidRequestException(sprintf('The phone parameter is required for %s', $rail));
            }

            return null;
        }

        $phone = $this->normalisePhone($phone);

        // An M-Pesa wallet can't be on a Telebirr number, and the API's error is
        // far less clear than this one.
        if ($rail === Rail::MPESA && !str_starts_with($phone, '2517')) {
            throw new InvalidRequestException('M-Pesa needs a phone number starting 2517');
        }

        return $phone;
    }
}
