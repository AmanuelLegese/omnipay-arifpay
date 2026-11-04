<?php

namespace Omnipay\ArifPay\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\ResponseInterface;

/**
 * Create a checkout session and redirect the customer to ArifPay's hosted page.
 *
 * Omnipay's parameters map onto ArifPay's as: transactionId to nonce, returnUrl
 * to successUrl, and transactionReference (on later calls) to sessionId.
 *
 * Give either `items`, or `amount` and it becomes a single line item. ArifPay
 * prices a session from its items alone and has no amount field, so when both
 * are given they must agree.
 *
 * @link https://developer.arifpay.net/docs/create-checkout-session
 */
class PurchaseRequest extends AbstractRequest
{
    public function getErrorUrl(): ?string
    {
        return $this->getParameter('errorUrl');
    }

    /**
     * Where to send the customer when the payment fails. ArifPay requires it.
     *
     * @return $this
     */
    public function setErrorUrl(?string $value): self
    {
        return $this->setParameter('errorUrl', $value);
    }

    public function getPhone(): ?string
    {
        return $this->getParameter('phone') ?? $this->getCard()?->getPhone();
    }

    /**
     * The customer's phone. Falls back to the `card` parameter's phone.
     *
     * @return $this
     */
    public function setPhone(?string $value): self
    {
        return $this->setParameter('phone', $value);
    }

    public function getEmail(): ?string
    {
        return $this->getParameter('email') ?? $this->getCard()?->getEmail();
    }

    /**
     * @return $this
     */
    public function setEmail(?string $value): self
    {
        return $this->setParameter('email', $value);
    }

    public function getExpireDate(): \DateTimeInterface|string|null
    {
        return $this->getParameter('expireDate');
    }

    /**
     * When the session expires. Must be in the future.
     *
     * @return $this
     */
    public function setExpireDate(\DateTimeInterface|string|null $value): self
    {
        return $this->setParameter('expireDate', $value);
    }

    public function getLang(): ?string
    {
        return $this->getParameter('lang');
    }

    /**
     * @return $this
     */
    public function setLang(?string $value): self
    {
        return $this->setParameter('lang', $value);
    }

    public function getSplitToAccount(): ?string
    {
        return $this->getParameter('splitToAccount');
    }

    /**
     * The registered email of the sub-account to attribute this payment to.
     *
     * @return $this
     */
    public function setSplitToAccount(?string $value): self
    {
        return $this->setParameter('splitToAccount', $value);
    }

    public function getData(): array
    {
        $this->validate('apiKey', 'transactionId', 'returnUrl', 'cancelUrl', 'errorUrl', 'notifyUrl');

        $data = [
            'nonce' => $this->getTransactionId(),
            'successUrl' => $this->getReturnUrl(),
            'cancelUrl' => $this->getCancelUrl(),
            'errorUrl' => $this->getErrorUrl(),
            'notifyUrl' => $this->getNotifyUrl(),
            'items' => $this->buildItems(),
        ];

        $phone = $this->getPhone();

        if ($phone !== null && $phone !== '') {
            $data['phone'] = $this->normalisePhone($phone);
        }

        $expireDate = $this->getExpireDate();

        if ($expireDate !== null && $expireDate !== '') {
            $data['expireDate'] = $this->formatDate($expireDate);
        }

        // Paying methods and beneficiaries are omitted on purpose: the gateway
        // fills them in from the merchant account.
        return $data + array_filter([
            'email' => $this->getEmail(),
            'lang' => $this->getLang(),
            'currency' => $this->getCurrency(),
            'splitToAccount' => $this->getSplitToAccount(),
        ], static fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * @throws InvalidRequestException
     */
    protected function buildItems(): array
    {
        $bag = $this->getItems();

        if ($bag === null || $bag->count() === 0) {
            $this->validate('amount');

            return [[
                'name' => $this->getDescription() ?: 'Payment',
                'quantity' => 1,
                'price' => (float) $this->getAmount(),
            ]];
        }

        $items = [];
        $total = 0;

        foreach ($bag as $item) {
            $line = [
                'name' => $item->getName(),
                'quantity' => (int) $item->getQuantity(),
                'price' => (float) $item->getPrice(),
            ];

            if ($item->getDescription()) {
                $line['description'] = $item->getDescription();
            }

            $items[] = $line;
            $total += (int) round($line['price'] * $line['quantity'] * 100);
        }

        // Items set the price, so a stale `amount` would otherwise pass silently
        // and the customer would be charged something else.
        if ($this->getParameter('amount') !== null && $this->getAmountInteger() !== $total) {
            throw new InvalidRequestException(sprintf(
                'The amount %s does not match the item total %s',
                $this->getAmount(),
                number_format($total / 100, 2, '.', '')
            ));
        }

        return $items;
    }

    protected function getEndpoint(): string
    {
        return $this->route('/api/checkout/session', '/api/sandbox/c2b/session');
    }

    protected function createResponse(array $data, int $httpStatus): ResponseInterface
    {
        return new PurchaseResponse($this, $data, $httpStatus);
    }
}
