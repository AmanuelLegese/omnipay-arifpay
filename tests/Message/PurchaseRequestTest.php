<?php

namespace Omnipay\ArifPay\Tests\Message;

use Omnipay\ArifPay\Message\AbstractRequest;
use Omnipay\ArifPay\Message\PurchaseRequest;
use Omnipay\ArifPay\Message\PurchaseResponse;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PurchaseRequestTest extends TestCase
{
    private PurchaseRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->request->initialize([
            'apiKey' => 'test-key',
            'amount' => '100.00',
            'transactionId' => 'order-1234',
            'returnUrl' => 'https://example.com/success',
            'cancelUrl' => 'https://example.com/cancel',
            'errorUrl' => 'https://example.com/error',
            'notifyUrl' => 'https://example.com/notify',
        ]);
    }

    public function testMapsOmnipayParametersOntoArifPays(): void
    {
        $this->assertSame([
            'nonce' => 'order-1234',
            'successUrl' => 'https://example.com/success',
            'cancelUrl' => 'https://example.com/cancel',
            'errorUrl' => 'https://example.com/error',
            'notifyUrl' => 'https://example.com/notify',
            'items' => [['name' => 'Payment', 'quantity' => 1, 'price' => 100.0]],
        ], $this->request->getData());
    }

    public function testDescriptionNamesTheSingleItem(): void
    {
        $this->request->setDescription('Order 1234');

        $this->assertSame('Order 1234', $this->request->getData()['items'][0]['name']);
    }

    public function testOptionalFieldsAreSentWhenGiven(): void
    {
        $this->request->setPhone('251912345678');
        $this->request->setEmail('merchant@example.com');
        $this->request->setExpireDate(new \DateTimeImmutable('2027-12-31 23:59:59'));
        $this->request->setLang('AM');
        $this->request->setCurrency('ETB');
        $this->request->setSplitToAccount('store-east@example.com');

        $data = $this->request->getData();

        $this->assertSame('251912345678', $data['phone']);
        $this->assertSame('merchant@example.com', $data['email']);
        $this->assertSame('2027-12-31T23:59:59', $data['expireDate']);
        $this->assertSame('AM', $data['lang']);
        $this->assertSame('ETB', $data['currency']);
        $this->assertSame('store-east@example.com', $data['splitToAccount']);
    }

    public function testCustomerDetailsCanComeFromTheCard(): void
    {
        $this->request->setCard(['phone' => '0912345678', 'email' => 'buyer@example.com']);

        $data = $this->request->getData();

        $this->assertSame('251912345678', $data['phone']);
        $this->assertSame('buyer@example.com', $data['email']);
    }

    public function testBuildsLineItems(): void
    {
        $this->request->setAmount(null);
        $this->request->setItems([
            ['name' => 'Coffee', 'quantity' => 2, 'price' => '25.50', 'description' => 'Yirgacheffe'],
            ['name' => 'Cup', 'quantity' => 1, 'price' => '49.00'],
        ]);

        $this->assertSame([
            ['name' => 'Coffee', 'quantity' => 2, 'price' => 25.5, 'description' => 'Yirgacheffe'],
            ['name' => 'Cup', 'quantity' => 1, 'price' => 49.0],
        ], $this->request->getData()['items']);
    }

    public function testAmountMatchingTheItemsIsAccepted(): void
    {
        $this->request->setItems([['name' => 'A', 'quantity' => 2, 'price' => '50.00']]);

        $this->assertCount(1, $this->request->getData()['items']);
    }

    public function testAmountDisagreeingWithTheItemsIsRefused(): void
    {
        $this->request->setItems([['name' => 'A', 'quantity' => 1, 'price' => '60.00']]);

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('does not match the item total 60.00');
        $this->request->getData();
    }

    public function testNeedsAnAmountOrItems(): void
    {
        $this->request->setAmount(null);

        $this->expectException(InvalidRequestException::class);
        $this->request->getData();
    }

    #[DataProvider('requiredParameters')]
    public function testRequiredParameters(string $setter): void
    {
        $this->request->$setter(null);

        $this->expectException(InvalidRequestException::class);
        $this->request->getData();
    }

    public static function requiredParameters(): array
    {
        return [
            'apiKey' => ['setApiKey'],
            'nonce' => ['setTransactionId'],
            'successUrl' => ['setReturnUrl'],
            'cancelUrl' => ['setCancelUrl'],
            'errorUrl' => ['setErrorUrl'],
            'notifyUrl' => ['setNotifyUrl'],
        ];
    }

    #[DataProvider('phones')]
    public function testNormalisesPhones(string $given, ?string $expected): void
    {
        $this->request->setPhone($given);

        if ($expected === null) {
            $this->expectException(InvalidRequestException::class);
        }

        $this->assertSame($expected, $this->request->getData()['phone']);
    }

    public static function phones(): array
    {
        return [
            'canonical' => ['251912345678', '251912345678'],
            'plus' => ['+251912345678', '251912345678'],
            'local' => ['0912345678', '251912345678'],
            'local mpesa' => ['0712345678', '251712345678'],
            'spaced' => ['+251 91 234 5678', '251912345678'],
            'landline' => ['251112345678', null],
            'short' => ['25191234', null],
            'words' => ['bad', null],
        ];
    }

    public function testSendsToProductionByDefault(): void
    {
        $this->setMockHttpResponse('SessionCreated.txt');

        $response = $this->request->send();

        $this->assertInstanceOf(PurchaseResponse::class, $response);
        $request = $this->getMockedRequests()[0];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame(AbstractRequest::PRODUCTION_URL . '/api/checkout/session', (string) $request->getUri());
        $this->assertSame('test-key', $request->getHeaderLine('x-arifpay-key'));
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame('order-1234', json_decode((string) $request->getBody(), true)['nonce']);
    }

    public function testTestModeUsesTheSandbox(): void
    {
        $this->setMockHttpResponse('SessionCreated.txt');
        $this->request->setTestMode(true);
        $this->request->setSimMode('FAIL');
        $this->request->setFeeSource('MERCHANT');

        $this->request->send();

        $request = $this->getMockedRequests()[0];
        $this->assertSame(AbstractRequest::SANDBOX_URL . '/api/sandbox/c2b/session', (string) $request->getUri());
        $this->assertSame('FAIL', $request->getHeaderLine('x-arifpay-sim-mode'));
        $this->assertSame('MERCHANT', $request->getHeaderLine('x-arifpay-fee-source'));
    }

    public function testSimulatorHeadersNeverReachProduction(): void
    {
        $this->setMockHttpResponse('SessionCreated.txt');
        $this->request->setSimMode('FAIL');
        $this->request->setFeeSource('CUSTOMER');

        $this->request->send();

        $request = $this->getMockedRequests()[0];
        $this->assertFalse($request->hasHeader('x-arifpay-sim-mode'));
        $this->assertFalse($request->hasHeader('x-arifpay-fee-source'));
    }

    public function testBaseUrlCanBeConfigured(): void
    {
        $this->setMockHttpResponse('SessionCreated.txt');
        $this->request->setBaseUrl('https://merchant-gateway.example/');

        $this->request->send();

        $this->assertSame(
            'https://merchant-gateway.example/api/checkout/session',
            (string) $this->getMockedRequests()[0]->getUri()
        );
    }

    public function testSuccessIsARedirectAndNotACompletedPayment(): void
    {
        $this->setMockHttpResponse('SessionCreated.txt');

        $response = $this->request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isRedirect());
        $this->assertSame('GET', $response->getRedirectMethod());
        $this->assertSame('https://checkout.arifpay.org/checkout/4ECE0AB9D8BD', $response->getRedirectUrl());
        $this->assertSame('4ECE0AB9D8BD', $response->getTransactionReference());
        $this->assertSame('order-1234', $response->getTransactionId());
        $this->assertSame('10.00', $response->getAmount());
    }

    public function testDuplicateNonceIsRecognised(): void
    {
        $this->setMockHttpResponse('DuplicateNonce.txt');

        $response = $this->request->send();

        $this->assertFalse($response->isRedirect());
        $this->assertTrue($response->isDuplicateNonce());
        $this->assertSame('session already exist', $response->getMessage());
        $this->assertSame(400, $response->getHttpStatus());
    }

    public function testValidationErrorsAreFlattened(): void
    {
        $this->setMockHttpResponse('ValidationError.txt');

        $this->assertSame(
            "Validation Error: errorUrl: error url is mandatory, items: items can't be empty",
            $this->request->send()->getMessage()
        );
    }

    public function testAmountCodeIsExposed(): void
    {
        $this->setMockHttpResponse('AmountOverMaximum.txt');

        $response = $this->request->send();

        $this->assertSame('03', $response->getCode());
        $this->assertStringContainsString('cannot be greater than 43200', $response->getMessage());
    }

    /**
     * The sandbox really does answer over-maximum amounts like this, where the
     * Spring `error` key is a string rather than the envelope's boolean.
     */
    public function testSpringErrorsAreFailures(): void
    {
        $this->setMockHttpResponse('SpringError.txt');

        $response = $this->request->send();

        $this->assertTrue($response->isError());
        $this->assertFalse($response->isRedirect());
        $this->assertStringContainsString('cannot be greater than', $response->getMessage());
    }

    public function testProxyErrorsAreFailures(): void
    {
        $this->setMockHttpResponse('ProxyError.txt');

        $response = $this->request->send();

        $this->assertTrue($response->isError());
        $this->assertSame('failure to get a peer from the ring-balancer', $response->getMessage());
    }

    public function testNonJsonOutageIsAFailureWithAMessage(): void
    {
        $this->setMockHttpResponse('HtmlOutage.txt');

        $response = $this->request->send();

        $this->assertTrue($response->isError());
        $this->assertSame('Invalid response from ArifPay (HTTP 502)', $response->getMessage());
    }
}
