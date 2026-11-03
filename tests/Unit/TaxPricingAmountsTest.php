<?php

namespace Tests\Unit;

use App\Models\Settings\Local\TaxRate;
use App\Services\Pricing\TaxPricingService;
use App\Services\Settings\SystemSettingsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TaxPricingAmountsTest extends TestCase
{
    #[DataProvider('consumerAmounts')]
    public function test_included_vat_is_extracted_from_the_final_consumer_price(
        float $stored,
        float $rate,
        float $net,
        float $gross,
        float $tax
    ): void {
        $pricing = $this->pricing(true);
        $amounts = $pricing->amountsFromStored($stored, taxRate: $this->rate($rate));

        $this->assertSame(['net' => $net, 'gross' => $gross, 'tax' => $tax], $amounts);
        $this->assertSame($gross, round($amounts['net'] + $amounts['tax'], 2));
    }

    public static function consumerAmounts(): array
    {
        return [
            'HR advertised price' => [9.99, 25.0, 7.99, 9.99, 2.0],
            'DE advertised price' => [9.99, 19.0, 8.39, 9.99, 1.6],
            'AT advertised price' => [9.99, 20.0, 8.33, 9.99, 1.66],
            'DE ten percent promotion' => [8.991, 19.0, 7.55, 8.99, 1.44],
            'small gross price' => [0.02, 25.0, 0.02, 0.02, 0.0],
        ];
    }

    public function test_net_storage_keeps_rounding_net_before_adding_destination_vat(): void
    {
        $this->assertSame(
            ['net' => 0.03, 'gross' => 0.04, 'tax' => 0.01],
            $this->pricing(false)->amountsFromStored(0.027, taxRate: $this->rate(19))
        );
    }

    private function pricing(bool $includeTax): TaxPricingService
    {
        $settings = $this->createMock(SystemSettingsService::class);
        $settings->method('get')->with('store_pricing_prices_include_tax', false)->willReturn($includeTax);

        return new TaxPricingService($settings);
    }

    private function rate(float $percent): TaxRate
    {
        return new TaxRate(['rate' => $percent, 'rate_type' => 'percent']);
    }
}
