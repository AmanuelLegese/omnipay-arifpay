<?php

namespace Omnipay\ArifPay\Tests\Message;

use Omnipay\ArifPay\Gateway;
use Omnipay\ArifPay\Message\TransactionResponse;
use Omnipay\ArifPay\Rail;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Tests\TestCase;

class PaymentRailsTest extends TestCase
{
    private Gateway $gateway;

    private const SESSION = [
        'transactionId' => 'order-1234',
        'amount' => '10.00',
        'returnUrl' => 'https://example.com/s',
        'cancelUrl' => 'https://example.com/c',
        'errorUrl' => 'https://example.com/e',
        'notifyUrl' => 'https://example.com/n',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new Gateway($this->getHttpClient(), $this->getHttpRequest());
        $this->gateway->setApiKey('test-key');
    }

    private function lastPath(): string
    {
        return $this->getMockedRequests()[0]->getUri()->getPath();
    }

    private function lastBody(): array
    {
        return json_decode((string) $this->getMockedRequests()[0]->getBody(), true);
    }

    public function testDirectPurchaseSendsTheWholeSessionToTheRail(): void
    {
        $this->setMockHttpResponse('DirectAccepted.txt');

        $response = $this->gateway->directPurchase(self::SESSION + [
            'rail' => Rail::TELEBIRR,
            'phone' => '0912345678',
        ])->send();

        $this->assertSame('/api/checkout/telebirr-ussd/transfer/direct', $this->lastPath());
        $this->assertSame('251912345678', $this->lastBody()['phone']);
        $this->assertSame('order-1234', $this->lastBody()['nonce']);

        // An accept is not a payment: this is the response async rails give.
        $this->assertInstanceOf(TransactionResponse::class, $response);
        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isPending());
        $this->assertSame('USSD request sent to your phone', $response->getMessage());
        $this->assertSame('ED54BDB4D5BF2D', $response->getTransactionReference());
    }

    public function testDirectPurchaseNeedsAPhoneOnWalletRails(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('phone parameter is required for telebirr');

        $this->gateway->directPurchase(self::SESSION + ['rail' => Rail::TELEBIRR])->getData();
    }

    public function testMpesaRefusesATelebirrNumber(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('2517');

        $this->gateway->directPurchase(self::SESSION + ['rail' => Rail::MPESA, 'phone' => '251912345678'])->getData();
    }

    public function testMpesaAcceptsItsOwnRange(): void
    {
        $data = $this->gateway->directPurchase(self::SESSION + ['rail' => Rail::MPESA, 'phone' => '0712345678'])
            ->getData();

        $this->assertSame('251712345678', $data['phone']);
    }

    public function testZamzamNeedsAnAccountNumber(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('accountNumber');

        $this->gateway->directPurchase(self::SESSION + ['rail' => Rail::ZAMZAM])->getData();
    }

    public function testUnknownRailListsTheChoices(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Unknown rail "amole"');

        $this->gateway->directPurchase(self::SESSION + ['rail' => 'amole', 'phone' => '251912345678'])->getData();
    }

    public function testMissingProductionRouteExplainsTheEndpointParameter(): void
    {
        $this->setMockHttpResponse('DirectAccepted.txt');

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('pass it as the endpoint parameter');

        $this->gateway->directPurchase(self::SESSION + ['rail' => Rail::PAYPAL])->send();
    }

    public function testIssuedEndpointIsUsed(): void
    {
        $this->setMockHttpResponse('PayPalOrderProduction.txt');

        $response = $this->gateway->directPurchase(self::SESSION + [
            'rail' => Rail::PAYPAL,
            'endpoint' => '/issued/paypal',
        ])->send();

        $this->assertSame('/issued/paypal', $this->lastPath());
        $this->assertTrue($response->isRedirect());
        $this->assertSame('https://www.paypal.com/checkoutnow?token=5O190127TN364715T', $response->getRedirectUrl());
        $this->assertSame('5O190127TN364715T', $response->getOrderId());
        $this->assertSame('ED54BDB4D5BF2D', $response->getTransactionReference());
    }

    public function testEndpointCannotSendTheKeyToAnotherHost(): void
    {
        $this->expectException(InvalidRequestException::class);

        $this->gateway->directPurchase(self::SESSION + [
            'rail' => Rail::PAYPAL,
            'endpoint' => 'https://evil.example/collect',
        ])->send();
    }

    public function testSandboxSuccessSettlesImmediately(): void
    {
        $this->setMockHttpResponse('DirectSuccess.txt');
        $this->gateway->setTestMode(true);

        $response = $this->gateway->directPurchase(self::SESSION + [
            'rail' => Rail::TELEBIRR,
            'phone' => '251900000000',
        ])->send();

        $this->assertSame('/api/sandbox/c2b/telebirr-ussd/direct/transfer', $this->lastPath());
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('SUCCESS', $response->getStatus());
        $this->assertSame('0FCD55F3D3A3', $response->getTransactionReference());
        $this->assertSame('order-1234', $response->getTransactionId());
        $this->assertSame('SIM-1a31251a', $response->getReceipt());
        $this->assertSame('10.00', $response->getAmount());
        $this->assertSame('0.29', $response->getFee());
        $this->assertSame('TELEBIRR_USSD', $response->getPaymentType());

        // This url is just the merchant's own successUrl echoed back.
        $this->assertSame('https://example.com/s', $response->getUrl());
        $this->assertFalse($response->isRedirect());
    }

    public function testFailureComesWithHttp200(): void
    {
        $this->setMockHttpResponse('DirectSimFailure.txt');

        $response = $this->gateway->directPurchase(self::SESSION + [
            'rail' => Rail::TELEBIRR,
            'phone' => '251900000000',
        ])->send();

        $this->assertSame(200, $response->getHttpStatus());
        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isPending());
        $this->assertTrue($response->isFailed());
        $this->assertSame('Simulated failure', $response->getMessage());
    }

    /**
     * error:true alongside a SUCCESS transaction. The attempt failed even though
     * the session is paid, and the message must not be overwritten by the fields.
     */
    public function testRetryingAPaidSessionIsNotSuccess(): void
    {
        $this->setMockHttpResponse('AlreadySuccessful.txt');

        $response = $this->gateway->sessionPayment([
            'rail' => Rail::CBE,
            'transactionReference' => '0FCD55F3D3A3',
            'phone' => '251900000000',
        ])->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('SUCCESS', $response->getStatus());
        $this->assertSame('The Checkout is Already Successful', $response->getMessage());
    }

    public function testSessionPaymentSendsSessionAndPhone(): void
    {
        $this->setMockHttpResponse('DirectAccepted.txt');

        $this->gateway->sessionPayment([
            'rail' => Rail::NIB_EBIRR,
            'transactionReference' => 'SID',
            'phone' => '251912345678',
        ])->send();

        $this->assertSame('/api/checkout/nib-ebirr/transfer', $this->lastPath());
        $this->assertSame(['sessionId' => 'SID', 'phoneNumber' => '251912345678'], $this->lastBody());
    }

    public function testZamzamSessionPaymentSendsTheAccount(): void
    {
        $this->setMockHttpResponse('OtpSent.txt');

        $this->gateway->sessionPayment([
            'rail' => Rail::ZAMZAM,
            'transactionReference' => 'SID',
            'accountNumber' => '1000123456789',
        ])->send();

        $this->assertSame(['sessionId' => 'SID', 'accountNumber' => '1000123456789'], $this->lastBody());
    }

    public function testAhaduUsesThePhoneAsItsAccountNumber(): void
    {
        $this->gateway->setTestMode(true);
        $this->setMockHttpResponse('OtpSent.txt');

        $this->gateway->sessionPayment([
            'rail' => Rail::AHADU,
            'transactionReference' => 'SID',
            'phone' => '251912345678',
        ])->send();

        $this->assertSame('/api/sandbox/c2b/ahadu/initiate', $this->lastPath());
        $this->assertSame('251912345678', $this->lastBody()['accountNumber']);
        $this->assertSame('251912345678', $this->lastBody()['phoneNumber']);
    }

    public function testBoaAnswersWithHtml(): void
    {
        $this->setMockHttpResponse('BoaHtml.txt');

        $response = $this->gateway->sessionPayment(['rail' => Rail::BOA, 'transactionReference' => 'SID'])->send();

        $this->assertSame('/api/checkout/boa/direct/transfer', $this->lastPath());
        $this->assertSame(['sessionId' => 'SID'], $this->lastBody());
        $this->assertFalse($response->isError());
        $this->assertTrue($response->isPending());
        $this->assertStringContainsString('<form', $response->getHtml());
    }

    public function testHtmlOnOtherRailsIsStillAnError(): void
    {
        $this->setMockHttpResponse('BoaHtml.txt');

        $response = $this->gateway->sessionPayment([
            'rail' => Rail::CBE,
            'transactionReference' => 'SID',
            'phone' => '251912345678',
        ])->send();

        $this->assertTrue($response->isError());
        $this->assertNull($response->getHtml());
    }

    /**
     * On this rail data.sessionId is Mastercard's, so reading it as ArifPay's
     * would look up the wrong session.
     */
    public function testMastercardHostedIdIsNotTheReference(): void
    {
        $this->setMockHttpResponse('MastercardHosted.txt');
        $this->gateway->setTestMode(true);

        $response = $this->gateway->directPurchase(self::SESSION + [
            'rail' => Rail::MASTERCARD,
            'phone' => '251912345678',
        ])->send();

        $this->assertSame('0FCD55F3D3A3', $response->getTransactionReference());
        $this->assertSame('SESSION0002123456789', $response->getHostedSessionId());
    }

    public function testPayPalSandboxUsesALowerCaseSessionKey(): void
    {
        $this->setMockHttpResponse('PayPalOrder.txt');
        $this->gateway->setTestMode(true);

        $response = $this->gateway->directPurchase(self::SESSION + ['rail' => Rail::PAYPAL])->send();

        $this->assertSame('3F8D3DAAD66D', $response->getTransactionReference());
        $this->assertTrue($response->isPending());
    }

    public function testVerifyOtp(): void
    {
        $this->setMockHttpResponse('DirectSuccess.txt');

        $response = $this->gateway->verifyOtp([
            'rail' => Rail::BINGET,
            'transactionReference' => 'SID',
            'otp' => '123456',
        ])->send();

        $this->assertSame('/api/checkout/binget/verify-otp', $this->lastPath());
        $this->assertSame(['sessionId' => 'SID', 'otp' => '123456'], $this->lastBody());
        $this->assertTrue($response->isSuccessful());
    }

    public function testZamzamOtpNeedsTheAccount(): void
    {
        $this->expectException(InvalidRequestException::class);

        $this->gateway->verifyOtp(['rail' => Rail::ZAMZAM, 'transactionReference' => 'SID', 'otp' => '1'])->getData();
    }

    public function testWrongOtpExposesItsCode(): void
    {
        $this->setMockHttpResponse('InvalidOtp.txt');

        $response = $this->gateway->verifyOtp([
            'rail' => Rail::BINGET,
            'transactionReference' => 'SID',
            'otp' => '000000',
        ])->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('00', $response->getCode());
    }

    public function testCaptureNeedsTheIssuedEndpoint(): void
    {
        $this->expectException(InvalidRequestException::class);

        $this->gateway->capture(['transactionReference' => 'SID', 'orderId' => 'O1'])->getData();
    }

    public function testCapturePostsOrderAndSession(): void
    {
        $this->setMockHttpResponse('DirectSuccess.txt');

        $this->gateway->capture([
            'transactionReference' => 'SID',
            'orderId' => 'O1',
            'endpoint' => '/issued/capture',
        ])->send();

        $this->assertSame('/issued/capture', $this->lastPath());
        $this->assertSame(['sessionId' => 'SID', 'orderId' => 'O1'], $this->lastBody());
    }

    public function testPayoutSendsTheSecretKey(): void
    {
        $this->gateway->setSecretKey('withdraw-secret');
        $this->setMockHttpResponse('DirectAccepted.txt');

        $response = $this->gateway->payout([
            'rail' => Rail::TELEBIRR,
            'transactionReference' => 'SID',
            'phone' => '251912345678',
            'endpoint' => '/issued/b2c/telebirr',
        ])->send();

        $request = $this->getMockedRequests()[0];
        $this->assertSame('/issued/b2c/telebirr', $request->getUri()->getPath());
        $this->assertSame('withdraw-secret', $request->getHeaderLine('x-arifpay-secret-key'));
        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isPending());
    }

    public function testProductionPayoutNeedsTheSecretKey(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('secretKey');

        $this->gateway->payout([
            'rail' => Rail::TELEBIRR,
            'transactionReference' => 'SID',
            'phone' => '251912345678',
            'endpoint' => '/issued',
        ])->getData();
    }

    public function testProductionPayoutHasNoPublishedRoute(): void
    {
        $this->gateway->setSecretKey('withdraw-secret');
        $this->setMockHttpResponse('DirectAccepted.txt');

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('endpoint parameter');

        $this->gateway->payout([
            'rail' => Rail::TELEBIRR,
            'transactionReference' => 'SID',
            'phone' => '251912345678',
        ])->send();
    }

    public function testSecretKeyStaysOffOrdinaryPayments(): void
    {
        $this->gateway->setSecretKey('withdraw-secret');
        $this->setMockHttpResponse('DirectAccepted.txt');

        $this->gateway->sessionPayment([
            'rail' => Rail::CBE,
            'transactionReference' => 'SID',
            'phone' => '251912345678',
        ])->send();

        $this->assertFalse($this->getMockedRequests()[0]->hasHeader('x-arifpay-secret-key'));
    }
}
