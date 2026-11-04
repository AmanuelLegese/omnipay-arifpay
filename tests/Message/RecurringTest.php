<?php

namespace Omnipay\ArifPay\Tests\Message;

use Omnipay\ArifPay\Gateway;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Tests\TestCase;

class RecurringTest extends TestCase
{
    private Gateway $gateway;

    private const PLAN = [
        'amount' => '10.00',
        'frequency' => 'MONTHLY',
        'startDate' => '2027-12-31T23:59:00',
        'name' => 'Monthly donation',
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

    private function lastUri(): string
    {
        $uri = $this->getMockedRequests()[0]->getUri();

        return $uri->getPath() . ($uri->getQuery() !== '' ? '?' . $uri->getQuery() : '');
    }

    public function testCreatesAPlan(): void
    {
        $this->setMockHttpResponse('RecurringCreated.txt');

        $response = $this->gateway->createRecurringPlan(self::PLAN)->send();

        $body = json_decode((string) $this->getMockedRequests()[0]->getBody(), true);

        $this->assertSame('/api/recuring/recurring-payment-session', $this->lastUri());
        $this->assertSame('Monthly donation', $body['recurringPaymentName']);
        $this->assertSame('https://example.com/s', $body['successUrl']);
        $this->assertArrayNotHasKey('status', $body);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame(201, $response->getHttpStatus());
        $this->assertSame('4a1cc60e-78bb-43a5-b1ff-be39e669eec4', $response->getTransactionReference());
    }

    public function testSandboxPrefix(): void
    {
        $this->gateway->setTestMode(true);
        $this->setMockHttpResponse('RecurringCreated.txt');

        $this->gateway->createRecurringPlan(self::PLAN)->send();

        $this->assertSame('/api/sandbox/recuring/recurring-payment-session', $this->lastUri());
    }

    public function testRefusesAnUnknownFrequency(): void
    {
        $this->expectException(InvalidRequestException::class);

        $this->gateway->createRecurringPlan(['frequency' => 'HOURLY'] + self::PLAN)->getData();
    }

    public function testUpdateSendsOnlyWhatChanged(): void
    {
        $this->setMockHttpResponse('RecurringCreated.txt');

        $this->gateway->updateRecurringPlan(['transactionReference' => 'U1', 'amount' => '12.00'])->send();

        $request = $this->getMockedRequests()[0];
        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('/api/recuring/update/recurring/U1', $this->lastUri());
        $this->assertEquals(['amount' => 12], json_decode((string) $request->getBody(), true));
    }

    public function testListsPlansWithFilters(): void
    {
        $this->setMockHttpResponse('RecurringCreated.txt');

        $this->gateway->fetchRecurringPlans(['frequency' => 'MONTHLY', 'page' => 0, 'size' => 5])->send();

        $this->assertSame('/api/recuring/recurring-sessions?frequency=MONTHLY&page=0&size=5', $this->lastUri());
    }

    public function testSubscribersAreValidatedBecauseArifPayDoesNot(): void
    {
        $this->expectException(InvalidRequestException::class);

        $this->gateway->addRecurringSubscribers([
            'transactionReference' => 'U1',
            'phoneNumbers' => ['251911661336', 'bad'],
        ])->getData();
    }

    public function testAddsSubscribers(): void
    {
        $this->setMockHttpResponse('RecurringCreated.txt');

        $this->gateway->addRecurringSubscribers([
            'transactionReference' => 'U1',
            'phoneNumbers' => ['0911661336'],
        ])->send();

        $this->assertSame('/api/recuring/recurring-payment-session/U1/subscribers', $this->lastUri());
        $this->assertSame(
            ['phoneNumbers' => ['251911661336']],
            json_decode((string) $this->getMockedRequests()[0]->getBody(), true)
        );
    }

    public function testSubscriberRoutes(): void
    {
        $phone = ['transactionReference' => 'U1', 'phone' => '0911661336'];
        $routes = [
            ['fetchRecurringSubscribers', ['transactionReference' => 'U1'], 'GET', '/api/recuring/recurring-sessions/U1/subscribers'],
            ['reinitiateRecurringSubscriber', $phone, 'POST', '/api/recuring/recurring-payment-session/U1/reinitiate/subscriber/251911661336'],
            ['toggleRecurringSubscriber', $phone, 'POST', '/api/recuring/U1/subscriber/251911661336/status'],
            ['fetchRecurringReport', $phone, 'GET', '/api/recuring/report/U1/251911661336'],
            ['fetchRecurringSettings', [], 'GET', '/api/recuring/settings'],
            ['processRecurringOverdue', [], 'GET', '/api/recuring/process/outdated/payments'],
            ['processRecurringPending', [], 'GET', '/api/recuring/process/pending/payments'],
            ['processRecurringPending', ['transactionReference' => 'U1'], 'GET', '/api/recuring/process/pending/payments/U1'],
        ];

        foreach ($routes as $index => [$method, $parameters, $verb, $path]) {
            $this->setMockHttpResponse('RecurringCreated.txt');
            $this->gateway->$method($parameters)->send();

            $request = $this->getMockedRequests()[$index];
            $uri = $request->getUri();
            $this->assertSame($verb, $request->getMethod(), $method);
            $this->assertSame($path, $uri->getPath(), $method);
        }
    }

    public function testUpdatesSettings(): void
    {
        $this->setMockHttpResponse('RecurringCreated.txt');

        $this->gateway->updateRecurringSettings([
            'maxRetryCount' => 3,
            'retryIntervalHours' => 12,
            'retryStartAfterPayments' => 0,
        ])->send();

        $request = $this->getMockedRequests()[0];
        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame(
            ['maxRetryCount' => 3, 'retryIntervalHours' => 12, 'retryStartAfterPayments' => 0],
            json_decode((string) $request->getBody(), true)
        );
    }

    public function testRecurringErrorsHaveTheirOwnShape(): void
    {
        $this->setMockHttpResponse('RecurringReinitiateRefused.txt');

        $response = $this->gateway->reinitiateRecurringSubscriber([
            'transactionReference' => 'U1',
            'phone' => '251911661336',
        ])->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertSame(
            'Subscriber is not in CANCELLED status and cannot be reinitiated',
            $response->getMessage()
        );
    }

    public function testProcessingRepliesInPlainText(): void
    {
        $this->setMockHttpResponse('RecurringProcessed.txt');

        $response = $this->gateway->processRecurringPending()->send();

        $this->assertTrue($response->isSuccessful());
        $this->assertStringStartsWith('Processed 2 pending subscribers', $response->getBody());
    }

    public function testPlainTextErrorsAreFailures(): void
    {
        $this->setMockHttpResponse('HtmlOutage.txt');

        $response = $this->gateway->processRecurringPending()->send();

        $this->assertFalse($response->isSuccessful());
    }
}
