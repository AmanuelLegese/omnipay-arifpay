<?php

namespace Omnipay\ArifPay\Message;

use Omnipay\Common\Message\ResponseInterface;

/**
 * Check a payment when the customer returns from the hosted page.
 *
 * The same lookup as fetchTransaction(), under Omnipay's name for this moment.
 * Omnipay asks for the same arguments as purchase(), and the `amount` among them
 * is checked against the session: see CompletePurchaseResponse. Extras such as the
 * URLs are ignored.
 *
 * Unlike most gateways, nothing is read from the incoming request: ArifPay does
 * not document any parameters on its redirects, and treats them as UX only. So
 * add the transactionReference you stored. Without it the nonce is used, which
 * only works in production.
 */
class CompletePurchaseRequest extends FetchTransactionRequest
{
    protected function createResponse(array $data, int $httpStatus): ResponseInterface
    {
        return new CompletePurchaseResponse($this, $data, $httpStatus);
    }
}
