<?php

namespace Omnipay\ArifPay\Tests;

use Omnipay\ArifPay\Rail;
use PHPUnit\Framework\TestCase;

class RailTest extends TestCase
{
    public function testEveryRailCanStartAPaymentInTheSandbox(): void
    {
        foreach (Rail::all() as $rail) {
            $paths = array_filter([
                Rail::path($rail, Rail::FLOW_DIRECT, true),
                Rail::path($rail, Rail::FLOW_SESSION, true),
            ]);

            $this->assertNotEmpty($paths, $rail);
            foreach ($paths as $path) {
                $this->assertStringStartsWith('/api/sandbox/c2b/', $path, $rail);
            }
        }
    }

    public function testProductionPathsNeverPointAtTheSandbox(): void
    {
        foreach (Rail::all() as $rail) {
            foreach ([Rail::FLOW_DIRECT, Rail::FLOW_SESSION, Rail::FLOW_OTP, Rail::FLOW_PAYOUT] as $flow) {
                $path = Rail::path($rail, $flow, false);

                if ($path !== null) {
                    $this->assertStringStartsWith('/api/checkout/', $path, "$rail $flow");
                }
            }
        }
    }

    /**
     * ArifPay issues these at go-live and asks for them not to be hard-coded.
     */
    public function testRetractedProductionRoutesStayUnpublished(): void
    {
        foreach ([Rail::PAYPAL, Rail::MASTERCARD, Rail::VISA, Rail::PSS] as $rail) {
            $this->assertNull(Rail::path($rail, Rail::FLOW_DIRECT, false), $rail);
        }

        foreach ([Rail::TELEBIRR, Rail::CBE, Rail::MPESA] as $rail) {
            $this->assertNull(Rail::path($rail, Rail::FLOW_PAYOUT, false), $rail);
            $this->assertNotNull(Rail::path($rail, Rail::FLOW_PAYOUT, true), $rail);
        }
    }

    public function testRoutesThatDifferBetweenEnvironments(): void
    {
        $this->assertSame('/api/checkout/yaya/direct/transfer', Rail::path(Rail::YAYA, Rail::FLOW_DIRECT, false));
        $this->assertSame('/api/sandbox/c2b/yaya/transfer/direct', Rail::path(Rail::YAYA, Rail::FLOW_DIRECT, true));
        $this->assertSame('/api/checkout/binget/verify-otp', Rail::path(Rail::BINGET, Rail::FLOW_OTP, false));
        $this->assertSame('/api/sandbox/c2b/binget/verifyOTP', Rail::path(Rail::BINGET, Rail::FLOW_OTP, true));
        $this->assertSame('/api/checkout/v2/cbe/direct/transfer', Rail::path(Rail::CBE, Rail::FLOW_DIRECT, false));
        $this->assertNull(Rail::path(Rail::MPESA, Rail::FLOW_DIRECT, true));
    }
}
