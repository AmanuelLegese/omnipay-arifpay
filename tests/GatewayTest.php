<?php

namespace Omnipay\ArifPay\Tests;

use Omnipay\ArifPay\Gateway;
use Omnipay\ArifPay\Message\Notification;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Common\Message\NotificationInterface;
use Omnipay\Tests\GatewayTestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

class GatewayTest extends GatewayTestCase
{
    private const WEBHOOK = [
        'uuid' => '0FCD55F3D3A3',
        'nonce' => 'order-1234',
        'phone' => '251900000000',
        'paymentMethod' => 'TELEBIRR_USSD',
        'totalAmount' => 10.0,
        'transactionStatus' => 'SUCCESS',
        'transaction' => ['transactionId' => 'FORGED-RECEIPT', 'transactionStatus' => 'SUCCESS', 'fee' => 0.288],
        'sessionId' => '0FCD55F3D3A3',
        'notificationUrl' => 'https://example.com/n',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new Gateway($this->getHttpClient(), $this->getHttpRequest());
        $this->gateway->setApiKey('test-key');
    }

    /**
     * The inherited test expects acceptNotification() to work on an empty request,
     * which would defeat its purpose: an empty body must not produce a notification.
     */
    public function testSupportsAcceptNotification(): void
    {
        $this->assertTrue($this->gateway->supportsAcceptNotification());

        $this->expectException(InvalidRequestException::class);
        $this->gateway->acceptNotification();
    }

    /**
     * Omnipay::create('ArifPay') resolves Omnipay\ArifPay\Gateway by convention.
     */
    public function testResolvesByConventionalName(): void
    {
        $this->assertSame('ArifPay', $this->gateway->getName());
        $this->assertSame('ArifPay', $this->gateway->getShortName());
    }

    public function testUnconfiguredApiKeyIsRefusedRatherThanSentBlank(): void
    {
        $gateway = new Gateway($this->getHttpClient(), $this->getHttpRequest());

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('apiKey');

        $gateway->fetchTransaction(['transactionReference' => 'SID'])->getData();
    }

    public function testRefundAndAuthorizeAreNotOffered(): void
    {
        $this->assertFalse($this->gateway->supportsRefund());
        $this->assertFalse($this->gateway->supportsAuthorize());
    }

    /**
     * Omnipay's documented contract, and it has to be an \\Exception: its example
     * error handling is `catch (\\Exception $e)`, which would miss a PHP Error.
     */
    public function testUnsupportedMethodsThrowBadMethodCallException(): void
    {
        foreach (['refund', 'authorize', 'completeAuthorize', 'createCard', 'updateCard', 'deleteCard'] as $method) {
            try {
                $this->gateway->$method(['amount' => '10.00']);
                $this->fail($method . ' should not be supported');
            } catch (\BadMethodCallException $e) {
                $this->assertStringContainsString($method, $e->getMessage());
            }
        }
    }

    private function gatewayForWebhook(array $body): Gateway
    {
        $request = HttpRequest::create('/notify', 'POST', [], [], [], [], json_encode($body));
        $gateway = new Gateway($this->getHttpClient(), $request);
        $gateway->setApiKey('test-key');

        return $gateway;
    }

    public function testNotificationStatusComesFromTheLookupNotTheWebhook(): void
    {
        // The core protection: an unsigned webhook claiming SUCCESS, on a session
        // that ArifPay says is still pending, must not read as paid.
        $this->setMockHttpResponse('SessionPending.txt');

        $notification = $this->gatewayForWebhook(self::WEBHOOK)->acceptNotification();

        $this->assertTrue($notification->isVerified());
        $this->assertSame('SUCCESS', $notification->getClaimedStatus());
        $this->assertSame('PENDING', $notification->getStatus());
        $this->assertSame(NotificationInterface::STATUS_PENDING, $notification->getTransactionStatus());
    }

    public function testConfirmedNotificationIsCompleted(): void
    {
        $this->setMockHttpResponse('SessionSuccess.txt');

        $notification = $this->gatewayForWebhook(self::WEBHOOK)->acceptNotification();

        $this->assertSame(NotificationInterface::STATUS_COMPLETED, $notification->getTransactionStatus());
        $this->assertSame('0FCD55F3D3A3', $notification->getTransactionReference());
        $this->assertSame('order-1234', $notification->getTransactionId());
        $this->assertSame('10.00', $notification->getAmount());
        $this->assertSame('SIM-1a31251a', $notification->getReceipt());
        $this->assertSame('TELEBIRR_USSD', $notification->getPaymentMethod());

        $requests = $this->getMockedRequests();
        $this->assertCount(1, $requests);
        $this->assertSame('GET', $requests[0]->getMethod());
        $this->assertSame('/api/checkout/session/0FCD55F3D3A3', $requests[0]->getUri()->getPath());
        $this->assertSame('test-key', $requests[0]->getHeaderLine('x-arifpay-key'));
    }

    public function testNotificationAmountIgnoresTheWebhookBody(): void
    {
        $this->setMockHttpResponse('SessionSuccess.txt');

        $notification = $this->gatewayForWebhook(['totalAmount' => 1.0] + self::WEBHOOK)->acceptNotification();

        $this->assertSame('10.00', $notification->getAmount());
    }

    public function testUnknownSessionIsRefused(): void
    {
        // What a session id forged, or belonging to another merchant, gets back.
        $this->setMockHttpResponse('SessionNotFound.txt');

        $this->expectException(InvalidResponseException::class);
        $this->gatewayForWebhook(self::WEBHOOK)->acceptNotification();
    }

    public function testRealSessionWithAnotherOrdersNonceIsRefused(): void
    {
        // Replaying a genuine paid session id with the nonce of an unpaid order.
        $this->setMockHttpResponse('SessionSuccess.txt');

        $this->expectException(InvalidResponseException::class);
        $this->gatewayForWebhook(['nonce' => 'someone-elses-order'] + self::WEBHOOK)->acceptNotification();
    }

    public function testLookupThatFindsADifferentSessionIsRefused(): void
    {
        $this->setMockHttpResponse('SessionSuccess.txt');

        $this->expectException(InvalidResponseException::class);
        $this->gatewayForWebhook(['sessionId' => '..', 'uuid' => '..'] + self::WEBHOOK)->acceptNotification();
    }

    public function testCraftedSessionIdCannotReachAnotherRoute(): void
    {
        $this->setMockHttpResponse('SessionNotFound.txt');

        try {
            $this->gatewayForWebhook(['sessionId' => '../../recuring/settings'] + self::WEBHOOK)->acceptNotification();
            $this->fail('Expected the lookup to be refused');
        } catch (InvalidResponseException) {
            $this->assertSame(
                '/api/checkout/session/..%2F..%2Frecuring%2Fsettings',
                $this->getMockedRequests()[0]->getUri()->getPath()
            );
        }
    }

    public function testWebhookWithoutSessionIdIsRefused(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->gatewayForWebhook(['transactionStatus' => 'SUCCESS'])->acceptNotification();
    }

    public function testUnverifiedNotificationIsExplicitAboutIt(): void
    {
        $body = [
            'sessionId' => '4a7c8b21',
            'nonce' => 'tx-nonce',
            'totalAmount' => 500.0,
            'transactionStatus' => 'SUCCESS',
            'paymentMethod' => 'MPESA_BANK',
            'transaction' => ['transactionId' => 'TXN-1', 'transactionStatus' => 'SUCCESS'],
        ];

        $notification = $this->gatewayForWebhook($body)->acceptNotification(['verify' => false]);

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertFalse($notification->isVerified());
        $this->assertSame(NotificationInterface::STATUS_COMPLETED, $notification->getTransactionStatus());
        $this->assertSame('500.00', $notification->getAmount());
        $this->assertSame('TXN-1', $notification->getReceipt());
        $this->assertCount(0, $this->getMockedRequests());
    }

    public function testUnknownStatusIsNeverCompleted(): void
    {
        $notification = $this->gatewayForWebhook(['transactionStatus' => 'REVERSED'] + self::WEBHOOK)
            ->acceptNotification(['verify' => false]);

        $this->assertSame(NotificationInterface::STATUS_FAILED, $notification->getTransactionStatus());
    }
}
