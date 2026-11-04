<?php

namespace Omnipay\ArifPay\Tests\Message;

use Omnipay\ArifPay\Gateway;
use Omnipay\ArifPay\Message\CompletePurchaseRequest;
use Omnipay\ArifPay\Message\VerifyTransactionResponse;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Tests\TestCase;

class TransactionLookupTest extends TestCase
{
    private const PURCHASE = [
        'transactionId' => 'order-1234',
        'amount' => '10.00',
        'description' => 'Order 1234',
        'phone' => '251900000000',
        'returnUrl' => 'https://example.com/s',
        'cancelUrl' => 'https://example.com/c',
        'errorUrl' => 'https://example.com/e',
        'notifyUrl' => 'https://example.com/n',
    ];

    private Gateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new Gateway($this->getHttpClient(), $this->getHttpRequest());
        $this->gateway->setApiKey('test-key');
    }

    private function lastUri(): string
    {
        $uri = $this->getMockedRequests()[0]->getUri();

        return $uri->getPath() . ($uri->getQuery() !== '' ? '?' . $uri->getQuery() : '');
    }

    public function testFetchesBySessionId(): void
    {
        $this->setMockHttpResponse('SessionSuccess.txt');

        $response = $this->gateway->fetchTransaction(['transactionReference' => 'SID'])->send();

        $this->assertSame('/api/checkout/session/SID', $this->lastUri());
        $this->assertSame('GET', $this->getMockedRequests()[0]->getMethod());
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('0FCD55F3D3A3', $response->getTransactionReference());
        $this->assertSame('order-1234', $response->getTransactionId());
        $this->assertSame('10.00', $response->getAmount());
        $this->assertSame('251900000000', $response->getPhone());
    }

    public function testFetchesByNonce(): void
    {
        $this->setMockHttpResponse('SessionSuccess.txt');

        $this->gateway->fetchTransaction(['transactionId' => 'order/1234'])->send();

        $this->assertSame('/api/checkout/getSessionByNonce/order%2F1234', $this->lastUri());
    }

    public function testNonceLookupHasNoSandboxRoute(): void
    {
        $this->gateway->setTestMode(true);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('no ArifPay sandbox route');

        $this->gateway->fetchTransaction(['transactionId' => 'order-1234'])->send();
    }

    public function testSessionLookupUsesTheSandboxTwin(): void
    {
        $this->gateway->setTestMode(true);
        $this->setMockHttpResponse('SessionPending.txt');

        $response = $this->gateway->fetchTransaction(['transactionReference' => 'SID'])->send();

        $this->assertSame('/api/sandbox/checkout/session/SID', $this->lastUri());
        $this->assertTrue($response->isPending());
        $this->assertFalse($response->isSuccessful());
    }

    public function testNeedsAnIdentifier(): void
    {
        $this->expectException(InvalidRequestException::class);

        $this->gateway->fetchTransaction()->getData();
    }

    public function testStatuses(): void
    {
        $this->setMockHttpResponse('SessionFailed.txt');
        $this->assertTrue($this->gateway->fetchTransaction(['transactionReference' => 'S'])->send()->isFailed());

        $this->setMockHttpResponse('SessionCancelled.txt');
        $cancelled = $this->gateway->fetchTransaction(['transactionReference' => 'S'])->send();
        $this->assertTrue($cancelled->isCancelled());
        $this->assertFalse($cancelled->isPending());
    }

    public function testNotFoundIsNeitherPaidNorPending(): void
    {
        $this->setMockHttpResponse('SessionNotFound.txt');

        $response = $this->gateway->fetchTransaction(['transactionReference' => 'NOPE'])->send();

        $this->assertTrue($response->isError());
        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isPending());
        $this->assertSame('session not found with the given id', $response->getMessage());
    }

    public function testBadApiKeyIsAnError(): void
    {
        $this->setMockHttpResponse('ApiKeyNotFound.txt');

        $response = $this->gateway->fetchTransaction(['transactionReference' => 'S'])->send();

        $this->assertTrue($response->isError());
        $this->assertSame('API Key Not found', $response->getMessage());
    }

    public function testCompletePurchaseIgnoresTheIncomingRequest(): void
    {
        // The return is attacker-controlled, and ArifPay documents no parameters.
        $this->getHttpRequest()->query->set('sessionId', 'FROM-THE-BROWSER');

        $request = $this->gateway->completePurchase();

        $this->assertInstanceOf(CompletePurchaseRequest::class, $request);
        $this->expectException(InvalidRequestException::class);
        $request->getData();
    }

    public function testCompletePurchaseLooksUpTheStoredReference(): void
    {
        $this->setMockHttpResponse('SessionSuccess.txt');

        $response = $this->gateway->completePurchase(['transactionReference' => 'SID'])->send();

        $this->assertSame('/api/checkout/session/SID', $this->lastUri());
        $this->assertTrue($response->isSuccessful());
    }

    /**
     * Omnipay's rule: completePurchase() gets the same arguments as purchase().
     */
    public function testCompletePurchaseTakesThePurchaseArguments(): void
    {
        $this->setMockHttpResponse('SessionSuccess.txt');

        $response = $this->gateway->completePurchase(self::PURCHASE)->send();

        $this->assertSame('/api/checkout/getSessionByNonce/order-1234', $this->lastUri());
        $this->assertTrue($response->isSuccessful());
        $this->assertFalse($response->isAmountMismatch());
    }

    public function testCompletePurchaseRefusesTheWrongAmount(): void
    {
        $this->setMockHttpResponse('SessionSuccess.txt');

        $response = $this->gateway->completePurchase(['amount' => '999.00'] + self::PURCHASE)->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isAmountMismatch());
        $this->assertSame('SUCCESS', $response->getStatus());
        $this->assertSame('The session total 10.00 does not match the expected amount 999.00', $response->getMessage());
    }

    public function testCompletePurchaseCannotBeSatisfiedByAnUnpaidSession(): void
    {
        $this->setMockHttpResponse('SessionPending.txt');

        $response = $this->gateway->completePurchase(self::PURCHASE)->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isPending());
    }

    public function testFetchTransactionDoesNotCheckTheAmount(): void
    {
        $this->setMockHttpResponse('SessionSuccess.txt');

        $response = $this->gateway->fetchTransaction(['transactionReference' => 'S', 'amount' => '999.00'])->send();

        $this->assertTrue($response->isSuccessful());
    }

    public function testCompletePurchaseInTheSandboxExplainsWhatItNeeds(): void
    {
        $this->gateway->setTestMode(true);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('pass the transactionReference');

        $this->gateway->completePurchase(self::PURCHASE)->send();
    }

    public function testReceiptLookup(): void
    {
        $this->setMockHttpResponse('ReceiptLookup.txt');

        $response = $this->gateway->fetchTransactionByReceipt([
            'receipt' => 'BCI6FETYVI',
            'paymentType' => 'TELEBIRR_USSD',
        ])->send();

        $this->assertSame('/api/checkout/getSessionByTransactionId', $this->lastUri());
        $this->assertSame(
            ['transactionId' => 'BCI6FETYVI', 'paymentType' => 'TELEBIRR_USSD'],
            json_decode((string) $this->getMockedRequests()[0]->getBody(), true)
        );
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('ED54BDB4D5BF2D', $response->getTransactionReference());
        $this->assertSame('100.00', $response->getAmount());
        $this->assertSame('TELEBIRR_USSD', $response->getPaymentType());
    }

    public function testVerifyListsAttempts(): void
    {
        $this->setMockHttpResponse('VerifyList.txt');

        $response = $this->gateway->verifyTransaction(['transactionReference' => 'SID'])->send();

        $this->assertSame('/api/verify/transaction?uuid=SID', $this->lastUri());
        $this->assertInstanceOf(VerifyTransactionResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('BCI6FETYVI', $response->getTransactions()[0]['transactionId']);
    }

    public function testVerifyOfAnUnknownSessionIsAnEmptyList(): void
    {
        $this->setMockHttpResponse('VerifyEmpty.txt');

        $response = $this->gateway->verifyTransaction(['transactionReference' => 'NOPE'])->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isError());
        $this->assertSame([], $response->getTransactions());
    }

    public function testVoid(): void
    {
        $this->setMockHttpResponse('SessionCancelled.txt');

        $response = $this->gateway->void(['transactionReference' => 'SID'])->send();

        $this->assertSame('/api/checkout/session/cancel/SID', $this->lastUri());
        $this->assertSame('POST', $this->getMockedRequests()[0]->getMethod());
        $this->assertTrue($response->isSuccessful());
        $this->assertTrue($response->isCancelled());
    }

    public function testVoidThatFindsAPaidSessionIsNotSuccessful(): void
    {
        $this->setMockHttpResponse('SessionSuccess.txt');

        $this->assertFalse($this->gateway->void(['transactionReference' => 'SID'])->send()->isSuccessful());
    }
}
