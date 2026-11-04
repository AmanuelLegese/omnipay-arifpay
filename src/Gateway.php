<?php

namespace Omnipay\ArifPay;

use Omnipay\ArifPay\Message\BankTransferRequest;
use Omnipay\ArifPay\Message\CaptureRequest;
use Omnipay\ArifPay\Message\CompletePurchaseRequest;
use Omnipay\ArifPay\Message\DirectPurchaseRequest;
use Omnipay\ArifPay\Message\FetchBankAccountRequest;
use Omnipay\ArifPay\Message\FetchBankTransferRequest;
use Omnipay\ArifPay\Message\FetchTransactionByReceiptRequest;
use Omnipay\ArifPay\Message\FetchTransactionRequest;
use Omnipay\ArifPay\Message\Notification;
use Omnipay\ArifPay\Message\PayoutRequest;
use Omnipay\ArifPay\Message\PurchaseRequest;
use Omnipay\ArifPay\Message\Recurring\AddRecurringSubscribersRequest;
use Omnipay\ArifPay\Message\Recurring\CreateRecurringPlanRequest;
use Omnipay\ArifPay\Message\Recurring\FetchRecurringPlansRequest;
use Omnipay\ArifPay\Message\Recurring\FetchRecurringReportRequest;
use Omnipay\ArifPay\Message\Recurring\FetchRecurringSettingsRequest;
use Omnipay\ArifPay\Message\Recurring\FetchRecurringSubscribersRequest;
use Omnipay\ArifPay\Message\Recurring\ProcessRecurringOverdueRequest;
use Omnipay\ArifPay\Message\Recurring\ProcessRecurringPendingRequest;
use Omnipay\ArifPay\Message\Recurring\ReinitiateRecurringSubscriberRequest;
use Omnipay\ArifPay\Message\Recurring\ToggleRecurringSubscriberRequest;
use Omnipay\ArifPay\Message\Recurring\UpdateRecurringPlanRequest;
use Omnipay\ArifPay\Message\Recurring\UpdateRecurringSettingsRequest;
use Omnipay\ArifPay\Message\SessionPaymentRequest;
use Omnipay\ArifPay\Message\TransactionResponse;
use Omnipay\ArifPay\Message\VerifyOtpRequest;
use Omnipay\ArifPay\Message\VerifyTransactionRequest;
use Omnipay\ArifPay\Message\VoidRequest;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Common\Message\AbstractRequest;

/**
 * ArifPay gateway.
 *
 * ArifPay is an Ethiopian payment gateway covering Telebirr, CBE, M-Pesa, banks
 * and cards. testMode selects its sandbox, which has its own host and key.
 *
 * ArifPay has no refund API and no separate authorize step, so refund() and
 * authorize() are deliberately absent.
 *
 * @link https://developer.arifpay.net/docs
 */
class Gateway extends AbstractGateway
{
    public function getName(): string
    {
        return 'ArifPay';
    }

    /**
     * Omnipay promises BadMethodCallException for a feature a gateway lacks, such
     * as refund(). Defining those methods would make supportsRefund() report true,
     * since it checks method_exists(), so they are caught here instead. Left to
     * PHP they would raise an Error, which `catch (\Exception)` does not catch.
     *
     * @throws \BadMethodCallException
     */
    public function __call(string $name, array $arguments): never
    {
        throw new \BadMethodCallException(sprintf('The ArifPay gateway does not support %s()', $name));
    }

    public function getDefaultParameters(): array
    {
        return [
            'apiKey' => '',
            'secretKey' => '',
            'transferKey' => '',
            'baseUrl' => '',
            'simMode' => '',
            'feeSource' => '',
            'testMode' => false,
        ];
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
     * The withdrawal secret, which payouts need on top of the API key.
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
     * The sandbox's wallet-to-bank key.
     *
     * @return $this
     */
    public function setTransferKey(?string $value): self
    {
        return $this->setParameter('transferKey', $value);
    }

    public function getBaseUrl(): ?string
    {
        return $this->getParameter('baseUrl');
    }

    /**
     * The gateway host ArifPay confirmed for your merchant.
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
     * Sandbox only: `SUCCESS` or `FAIL`.
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
     * Sandbox only: `CUSTOMER` or `MERCHANT`.
     *
     * @return $this
     */
    public function setFeeSource(?string $value): self
    {
        return $this->setParameter('feeSource', $value);
    }

    /**
     * Create a checkout session and redirect to the hosted page.
     */
    public function purchase(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(PurchaseRequest::class, $parameters);
    }

    /**
     * Check a payment when the customer returns, by the reference you stored.
     */
    public function completePurchase(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(CompletePurchaseRequest::class, $parameters);
    }

    /**
     * Look up a session by `transactionReference` or `transactionId`.
     */
    public function fetchTransaction(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(FetchTransactionRequest::class, $parameters);
    }

    /**
     * Look up a session by provider `receipt` and `paymentType`.
     */
    public function fetchTransactionByReceipt(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(FetchTransactionByReceiptRequest::class, $parameters);
    }

    /**
     * List a session's attempts, for reconciliation. See the warning on
     * VerifyTransactionRequest before using this to decide an order is paid.
     */
    public function verifyTransaction(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(VerifyTransactionRequest::class, $parameters);
    }

    /**
     * Cancel an unpaid session.
     */
    public function void(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(VoidRequest::class, $parameters);
    }

    /**
     * Create a session and start paying on a `rail` in one call.
     */
    public function directPurchase(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(DirectPurchaseRequest::class, $parameters);
    }

    /**
     * Start paying on a `rail` for a session that already exists.
     */
    public function sessionPayment(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(SessionPaymentRequest::class, $parameters);
    }

    /**
     * Confirm a Binget, Zamzam or Ahadu payment with the customer's `otp`.
     */
    public function verifyOtp(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(VerifyOtpRequest::class, $parameters);
    }

    /**
     * Capture a PayPal order, at the `endpoint` issued at go-live.
     */
    public function capture(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(CaptureRequest::class, $parameters);
    }

    /**
     * Confirm an incoming webhook with ArifPay and parse it.
     *
     * ArifPay does not sign its webhooks, so this makes one authenticated lookup
     * and reports the status from that, not from the unauthenticated body. Unlike
     * the request methods it returns the notification directly, with nothing to
     * send(). See Notification for the reasoning.
     *
     * Pass `verify => false` for events the lookup can't serve, such as wallet to
     * bank. Every value on that notification is then only a claim: confirm it
     * yourself, for example with fetchBankTransfer(), before acting on it.
     *
     * @throws InvalidRequestException when the body is not an ArifPay event
     * @throws InvalidResponseException when ArifPay doesn't confirm the event
     */
    public function acceptNotification(array $parameters = []): Notification
    {
        if (($parameters['verify'] ?? true) === false) {
            return new Notification($this->httpRequest);
        }

        /** @var TransactionResponse $session */
        $session = $this->fetchTransaction([
            'transactionReference' => Notification::sessionIdFrom($this->httpRequest),
        ])->send();

        return new Notification($this->httpRequest, $session);
    }

    /**
     * Send money to a customer's wallet from an existing session (B2C).
     */
    public function payout(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(PayoutRequest::class, $parameters);
    }

    /**
     * Look up a bank account before transferring to it.
     */
    public function fetchBankAccount(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(FetchBankAccountRequest::class, $parameters);
    }

    /**
     * Send money from your wallet to a bank account.
     */
    public function bankTransfer(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(BankTransferRequest::class, $parameters);
    }

    /**
     * Check a bank transfer by `transactionId`.
     */
    public function fetchBankTransfer(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(FetchBankTransferRequest::class, $parameters);
    }

    public function createRecurringPlan(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(CreateRecurringPlanRequest::class, $parameters);
    }

    public function fetchRecurringPlans(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(FetchRecurringPlansRequest::class, $parameters);
    }

    public function updateRecurringPlan(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(UpdateRecurringPlanRequest::class, $parameters);
    }

    public function fetchRecurringSettings(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(FetchRecurringSettingsRequest::class, $parameters);
    }

    public function updateRecurringSettings(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(UpdateRecurringSettingsRequest::class, $parameters);
    }

    public function addRecurringSubscribers(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(AddRecurringSubscribersRequest::class, $parameters);
    }

    public function fetchRecurringSubscribers(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(FetchRecurringSubscribersRequest::class, $parameters);
    }

    public function reinitiateRecurringSubscriber(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(ReinitiateRecurringSubscriberRequest::class, $parameters);
    }

    public function toggleRecurringSubscriber(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(ToggleRecurringSubscriberRequest::class, $parameters);
    }

    public function fetchRecurringReport(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(FetchRecurringReportRequest::class, $parameters);
    }

    public function processRecurringPending(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(ProcessRecurringPendingRequest::class, $parameters);
    }

    public function processRecurringOverdue(array $parameters = []): AbstractRequest
    {
        return $this->createRequest(ProcessRecurringOverdueRequest::class, $parameters);
    }
}
