<?php

namespace Omnipay\ArifPay\Tests\Integration;

use Omnipay\ArifPay\Gateway;
use Omnipay\ArifPay\Message\AbstractResponse;
use Omnipay\ArifPay\Rail;
use Omnipay\Common\Http\Client;
use Omnipay\Common\Message\NotificationInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

/**
 * Against ArifPay's real sandbox, using the public key from its Postman
 * collection. Nothing here moves money. Run with:  make test-sandbox
 *
 * The gateway occasionally answers 503 "failure to get a peer from the
 * ring-balancer", which is a flake on ArifPay's side and not a failure here.
 */
#[Group('sandbox')]
class SandboxTest extends TestCase
{
    private const API_KEY = 'arif-sandbox-portal-key';
    private const OTP = '123456';
    private const ACCOUNT = '1000123456789';

    private Gateway $gateway;

    protected function setUp(): void
    {
        $this->gateway = $this->makeGateway(new HttpRequest());
    }

    private function makeGateway(HttpRequest $request): Gateway
    {
        $gateway = new Gateway(new Client(), $request);
        $gateway->setApiKey(self::API_KEY);
        $gateway->setTestMode(true);
        $gateway->setSimMode('SUCCESS');

        return $gateway;
    }

    private function session(array $extra = []): array
    {
        return $extra + [
            'transactionId' => 'omnipay-' . bin2hex(random_bytes(8)),
            'amount' => '10.00',
            'description' => 'Omnipay sandbox test',
            'returnUrl' => 'https://example.com/s',
            'cancelUrl' => 'https://example.com/c',
            'errorUrl' => 'https://example.com/e',
            'notifyUrl' => 'https://example.com/n',
            'expireDate' => '2030-12-31T23:59:00',
        ];
    }

    private function createSession(string $phone = '251911000000'): string
    {
        $response = $this->gateway->purchase($this->session(['phone' => $phone]))->send();
        $this->assertTrue($response->isRedirect(), (string) $response->getMessage());

        return $response->getTransactionReference();
    }

    private function assertSettled(AbstractResponse $response): void
    {
        $this->assertTrue($response->isSuccessful(), (string) $response->getMessage());
        $this->assertNotNull($response->getTransactionReference());
    }

    public function testHostedCheckoutLifecycle(): void
    {
        $parameters = $this->session();
        $created = $this->gateway->purchase($parameters)->send();

        $this->assertTrue($created->isRedirect(), (string) $created->getMessage());
        $this->assertSame('10.00', $created->getAmount());
        $this->assertStringContainsString('/checkout/', $created->getRedirectUrl());

        $duplicate = $this->gateway->purchase($parameters)->send();
        $this->assertTrue($duplicate->isDuplicateNonce());

        $reference = $created->getTransactionReference();
        $fetched = $this->gateway->completePurchase($parameters + ['transactionReference' => $reference])->send();

        $this->assertTrue($fetched->isPending());
        $this->assertFalse($fetched->isSuccessful());
        $this->assertSame($reference, $fetched->getTransactionReference());
        $this->assertSame($parameters['transactionId'], $fetched->getTransactionId());
        $this->assertSame('10.00', $fetched->getAmount());

        $voided = $this->gateway->void(['transactionReference' => $reference])->send();
        $this->assertTrue($voided->isSuccessful());
        $this->assertTrue($voided->isCancelled());
    }

    public function testCompletePurchaseChecksTheAmountOfAPaidSession(): void
    {
        $parameters = $this->session(['rail' => Rail::TELEBIRR, 'phone' => '251900000000']);
        $paid = $this->gateway->directPurchase($parameters)->send();
        $lookup = ['transactionReference' => $paid->getTransactionReference()];

        $this->assertTrue($this->gateway->completePurchase($parameters + $lookup)->send()->isSuccessful());

        $wrong = $this->gateway->completePurchase(['amount' => '11.00'] + $lookup)->send();
        $this->assertFalse($wrong->isSuccessful());
        $this->assertTrue($wrong->isAmountMismatch());
    }

    public function testMissingSessionIsAnError(): void
    {
        $missing = $this->gateway->fetchTransaction(['transactionReference' => 'NOPE'])->send();
        $this->assertTrue($missing->isError());
        $this->assertFalse($missing->isPending());
    }

    #[DataProvider('directRails')]
    public function testDirectPurchase(string $rail, string $phone, array $extra = []): void
    {
        $response = $this->gateway->directPurchase($this->session(['rail' => $rail, 'phone' => $phone] + $extra))->send();

        $this->assertSettled($response);
        $this->assertNotNull($response->getReceipt());

        $fetched = $this->gateway->fetchTransaction([
            'transactionReference' => $response->getTransactionReference(),
        ])->send();

        $this->assertTrue($fetched->isSuccessful());
        $this->assertSame($response->getReceipt(), $fetched->getReceipt());
    }

    public static function directRails(): array
    {
        return [
            'telebirr' => [Rail::TELEBIRR, '251900000000'],
            'cbe' => [Rail::CBE, '251911000000'],
            'awash' => [Rail::AWASH, '251911000000'],
            'boa' => [Rail::BOA, '251911000000'],
            'yaya' => [Rail::YAYA, '251911000000'],
            'kacha' => [Rail::KACHA, '251911000000'],
            'zamzam' => [Rail::ZAMZAM, '251911000000', ['accountNumber' => self::ACCOUNT]],
            'kaafi' => [Rail::KAAFI_EBIRR, '251911000000'],
            'coopay' => [Rail::COOPAY_EBIRR, '251911000000'],
            'wegagen' => [Rail::WEGAGEN_EBIRR, '251911000000'],
            'nib' => [Rail::NIB_EBIRR, '251911000000'],
            'ahadu ebirr' => [Rail::AHADU_EBIRR, '251911000000'],
            'visa' => [Rail::VISA, '251911000000'],
            'pss' => [Rail::PSS, '251911000000'],
            'mastercard' => [Rail::MASTERCARD, '251911000000'],
        ];
    }

    #[DataProvider('sessionRails')]
    public function testSessionPayment(string $rail, string $phone, array $extra = []): void
    {
        $response = $this->gateway->sessionPayment([
            'rail' => $rail,
            'transactionReference' => $this->createSession($phone),
            'phone' => $phone,
        ] + $extra)->send();

        $this->assertSettled($response);
    }

    public static function sessionRails(): array
    {
        return [
            'telebirr' => [Rail::TELEBIRR, '251900000000'],
            'mpesa' => [Rail::MPESA, '251700000000'],
            'cbe' => [Rail::CBE, '251911000000'],
            'awash' => [Rail::AWASH, '251911000000'],
            'yaya' => [Rail::YAYA, '251911000000'],
            'kacha' => [Rail::KACHA, '251911000000'],
            'kaafi' => [Rail::KAAFI_EBIRR, '251911000000'],
            'zamzam' => [Rail::ZAMZAM, '251911000000', ['accountNumber' => self::ACCOUNT]],
        ];
    }

    public function testSimulatedFailure(): void
    {
        $this->gateway->setSimMode('FAIL');

        $response = $this->gateway->directPurchase($this->session([
            'rail' => Rail::TELEBIRR,
            'phone' => '251900000000',
        ]))->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isFailed());
        $this->assertSame('Simulated failure', $response->getMessage());
    }

    public function testRetryingAPaidSessionIsNotSuccess(): void
    {
        $paid = $this->gateway->directPurchase($this->session([
            'rail' => Rail::TELEBIRR,
            'phone' => '251900000000',
        ]))->send();

        $retry = $this->gateway->sessionPayment([
            'rail' => Rail::TELEBIRR,
            'transactionReference' => $paid->getTransactionReference(),
            'phone' => '251900000000',
        ])->send();

        $this->assertFalse($retry->isSuccessful());
        $this->assertSame('SUCCESS', $retry->getStatus());
        $this->assertSame('The Checkout is Already Successful', $retry->getMessage());
    }

    public function testBingetOtpFlow(): void
    {
        $started = $this->gateway->directPurchase($this->session([
            'rail' => Rail::BINGET,
            'phone' => '251911000000',
        ]))->send();

        $this->assertTrue($started->isPending(), (string) $started->getMessage());
        $this->assertFalse($started->isRedirect());

        $parameters = ['rail' => Rail::BINGET, 'transactionReference' => $started->getTransactionReference()];

        // The SUCCESS sim header overrides the OTP check, so it must be off for this.
        $this->gateway->setSimMode(null);
        $wrong = $this->gateway->verifyOtp($parameters + ['otp' => '000000'])->send();
        $this->assertFalse($wrong->isSuccessful());
        $this->assertSame('Invalid OTP', $wrong->getMessage());

        $this->assertSettled($this->gateway->verifyOtp($parameters + ['otp' => self::OTP])->send());
    }

    public function testAhaduOtpFlow(): void
    {
        $reference = $this->createSession();

        $started = $this->gateway->sessionPayment([
            'rail' => Rail::AHADU,
            'transactionReference' => $reference,
            'phone' => '251911000000',
        ])->send();

        $this->assertTrue($started->isPending(), (string) $started->getMessage());

        $this->assertSettled($this->gateway->verifyOtp([
            'rail' => Rail::AHADU,
            'transactionReference' => $reference,
            'otp' => self::OTP,
            'accountNumber' => '251911000000',
        ])->send());
    }

    public function testPayPalReturnsAnApprovalRedirect(): void
    {
        $response = $this->gateway->directPurchase($this->session(['rail' => Rail::PAYPAL]))->send();

        $this->assertTrue($response->isRedirect(), (string) $response->getMessage());
        $this->assertStringContainsString('paypal.com', $response->getRedirectUrl());
        $this->assertNotNull($response->getOrderId());
        $this->assertNotNull($response->getTransactionReference());
    }

    #[DataProvider('payoutRails')]
    public function testPayout(string $rail, string $phone): void
    {
        $response = $this->gateway->payout([
            'rail' => $rail,
            'transactionReference' => $this->createSession($phone),
            'phone' => $phone,
        ])->send();

        $this->assertSettled($response);
    }

    public static function payoutRails(): array
    {
        return [
            'telebirr' => [Rail::TELEBIRR, '251900000000'],
            'cbe' => [Rail::CBE, '251911000000'],
            'mpesa' => [Rail::MPESA, '251700000000'],
        ];
    }

    public function testWalletToBank(): void
    {
        $this->gateway->setTransferKey('sandbox-w2b-key');

        $account = $this->gateway->fetchBankAccount([
            'accountNumber' => self::ACCOUNT,
            'shortCodes' => 'CBE',
        ])->send();

        $this->assertTrue($account->isSuccessful(), (string) $account->getMessage());
        $this->assertNotNull($account->getAccountName());

        $parameters = $this->session(['accountNumber' => self::ACCOUNT, 'shortCodes' => 'CBE', 'amount' => '5.00']);
        $transfer = $this->gateway->bankTransfer($parameters)->send();

        $this->assertTrue($transfer->isPending(), (string) $transfer->getMessage());
        $this->assertSame('5.00', $transfer->getAmount());
        $this->assertSame($parameters['transactionId'], $transfer->getTransactionId());

        $status = $this->gateway->fetchBankTransfer(['transactionId' => $parameters['transactionId']])->send();

        $this->assertTrue($status->isSuccessful(), (string) $status->getMessage());
    }

    public function testRecurringLifecycle(): void
    {
        $plan = $this->gateway->createRecurringPlan([
            'amount' => '10.00',
            'frequency' => 'MONTHLY',
            'startDate' => '2030-12-31T23:59:00',
            'name' => 'Omnipay sandbox test',
            'returnUrl' => 'https://example.com/s',
            'cancelUrl' => 'https://example.com/c',
            'errorUrl' => 'https://example.com/e',
            'notifyUrl' => 'https://example.com/n',
        ])->send();

        $this->assertTrue($plan->isSuccessful(), (string) $plan->getMessage());
        $uuid = $plan->getTransactionReference();
        $this->assertNotNull($uuid);

        $phone = ['transactionReference' => $uuid, 'phone' => '251911661336'];

        $added = $this->gateway->addRecurringSubscribers([
            'transactionReference' => $uuid,
            'phoneNumbers' => ['251911661336'],
        ])->send();
        $this->assertTrue($added->isSuccessful(), (string) $added->getMessage());
        $this->assertSame('251911661336', $added->getData()['successfullyAdded'][0]['phoneNumber']);

        $this->assertTrue($this->gateway->fetchRecurringPlans(['page' => 0, 'size' => 1])->send()->isSuccessful());
        $this->assertTrue($this->gateway->fetchRecurringSettings()->send()->isSuccessful());
        $this->assertTrue($this->gateway->fetchRecurringSubscribers([
            'transactionReference' => $uuid,
        ])->send()->isSuccessful());
        $this->assertTrue($this->gateway->fetchRecurringReport($phone)->send()->isSuccessful());
        $this->assertSame('12.00', number_format($this->gateway->updateRecurringPlan([
            'transactionReference' => $uuid,
            'amount' => '12.00',
        ])->send()->getData()['amount'], 2));

        $pending = $this->gateway->processRecurringPending(['transactionReference' => $uuid])->send();
        $this->assertTrue($pending->isSuccessful());
        $this->assertStringStartsWith('Processed', $pending->getBody());

        // Not cancelled yet, so this is refused in the recurring error shape.
        $refused = $this->gateway->reinitiateRecurringSubscriber($phone)->send();
        $this->assertFalse($refused->isSuccessful());
        $this->assertStringContainsString('CANCELLED', $refused->getMessage());
    }

    public function testNotificationIsCheckedAgainstTheRealSession(): void
    {
        $unpaid = $this->createSession();
        $lookup = $this->gateway->fetchTransaction(['transactionReference' => $unpaid])->send();

        $forged = HttpRequest::create('/notify', 'POST', [], [], [], [], json_encode([
            'sessionId' => $unpaid,
            'nonce' => $lookup->getTransactionId(),
            'transactionStatus' => 'SUCCESS',
            'totalAmount' => 10.0,
        ]));

        $notification = $this->makeGateway($forged)->acceptNotification();

        $this->assertSame('SUCCESS', $notification->getClaimedStatus());
        $this->assertSame(NotificationInterface::STATUS_PENDING, $notification->getTransactionStatus());
    }
}
