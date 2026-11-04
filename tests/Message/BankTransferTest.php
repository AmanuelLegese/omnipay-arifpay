<?php

namespace Omnipay\ArifPay\Tests\Message;

use Omnipay\ArifPay\Gateway;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Tests\TestCase;

class BankTransferTest extends TestCase
{
    private Gateway $gateway;

    private const TRANSFER = [
        'transactionId' => 'order-1234',
        'amount' => '5.00',
        'accountNumber' => '1000123456789',
        'shortCodes' => 'CBE',
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

    public function testProductionAccountPathKeepsItsMisspelling(): void
    {
        $this->setMockHttpResponse('BankAccountFound.txt');

        $response = $this->gateway->fetchBankAccount([
            'accountNumber' => '1000123456789',
            'shortCodes' => 'CBE',
        ])->send();

        $request = $this->getMockedRequests()[0];
        $this->assertSame('/api/checkout/b2b/acount/query', $request->getUri()->getPath());
        $this->assertSame('accountNumber=1000123456789&shortCodes=CBE', $request->getUri()->getQuery());
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('Sandbox Account Holder', $response->getAccountName());
        $this->assertSame('1000123456789', $response->getAccountNumber());
    }

    public function testSandboxAccountPathIsSpelledCorrectly(): void
    {
        $this->gateway->setTestMode(true);
        $this->setMockHttpResponse('BankAccountFound.txt');

        $this->gateway->fetchBankAccount(['accountNumber' => '1', 'shortCodes' => 'CBE'])->send();

        $this->assertSame('/api/sandbox/b2b/account/query', $this->getMockedRequests()[0]->getUri()->getPath());
    }

    public function testMissingAccountInASuccessfulEnvelopeIsNotSuccess(): void
    {
        $this->setMockHttpResponse('BankAccountMissing.txt');

        $response = $this->gateway->fetchBankAccount(['accountNumber' => '1', 'shortCodes' => 'CBE'])->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('Account not found', $response->getResponseDescription());
    }

    public function testNumericBankCodeIsRefused(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('not a numeric clearing code');

        $this->gateway->fetchBankAccount(['accountNumber' => '1', 'shortCodes' => '4889'])->getData();
    }

    public function testTransferIsAcceptedNotFinished(): void
    {
        $this->setMockHttpResponse('BankTransferAccepted.txt');

        $response = $this->gateway->bankTransfer(self::TRANSFER)->send();

        $request = $this->getMockedRequests()[0];
        $body = json_decode((string) $request->getBody(), true);

        $this->assertSame('/api/wallet-tobank/transfer', $request->getUri()->getPath());
        $this->assertSame('1000123456789', $body['accountNumber']);
        $this->assertSame('CBE', $body['shortCodes']);
        $this->assertSame(5, (int) $body['items'][0]['price']);
        $this->assertArrayNotHasKey('amount', $body);

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isPending());
        $this->assertSame('Transfer successful', $response->getMessage());
        $this->assertSame('order-1234', $response->getTransactionId());
        $this->assertSame('5.00', $response->getAmount());
    }

    public function testSandboxTransferSendsItsExtraFields(): void
    {
        $this->gateway->setTestMode(true);
        $this->gateway->setTransferKey('sandbox-w2b-key');
        $this->setMockHttpResponse('BankTransferAccepted.txt');

        $this->gateway->bankTransfer(self::TRANSFER)->send();

        $request = $this->getMockedRequests()[0];
        $this->assertSame('/api/sandbox/b2b/transfer', $request->getUri()->getPath());
        $this->assertSame('sandbox-w2b-key', $request->getHeaderLine('x-arifpay-transfer-key'));
        $this->assertEquals(5, json_decode((string) $request->getBody(), true)['amount']);
    }

    public function testTransferStatusInTheSandbox(): void
    {
        $this->gateway->setTestMode(true);
        $this->setMockHttpResponse('BankTransferStatus.txt');

        $response = $this->gateway->fetchBankTransfer(['transactionId' => 'order-1234'])->send();

        $this->assertSame(
            'nonce=order-1234',
            $this->getMockedRequests()[0]->getUri()->getQuery()
        );
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('Completed', $response->getResponseDescription());
    }

    public function testTransferStatusInProductionNeedsAnIssuedPath(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('endpoint parameter');

        $this->gateway->fetchBankTransfer(['transactionId' => 'order-1234'])->send();
    }

    public function testTransferStatusUsesAnIssuedPath(): void
    {
        $this->setMockHttpResponse('BankTransferStatus.txt');

        $this->gateway->fetchBankTransfer(['transactionId' => 'a b', 'endpoint' => '/issued/status'])->send();

        $uri = $this->getMockedRequests()[0]->getUri();
        $this->assertSame('/issued/status', $uri->getPath());
        $this->assertSame('nonce=a%20b', $uri->getQuery());
    }
}
