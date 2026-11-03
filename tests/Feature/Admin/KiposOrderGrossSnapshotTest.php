<?php

namespace Tests\Feature\Admin;

use App\Models\Sales\Order\Order;
use App\Services\Integrations\Kipos\KiposOrderService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class KiposOrderGrossSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::preventStrayRequests();
        app(SystemSettingsService::class)->putMany([
            'catalog_use_kipos_api' => true, 'kipos_api_enabled' => true,
            'kipos_api_base_uri' => 'https://erp.example.test/?route=',
        ]);
    }

    #[DataProvider('itemPrices')]
    public function test_erp_uses_recorded_gross_unit_price_and_preserves_historical_fallback(
        string $country,
        float $rate,
        float $net,
        float $tax,
        mixed $grossSnapshot,
        string $expectedPrice
    ): void {
        $order = $this->order($country, round($net + $tax, 2));
        $payload = $grossSnapshot === null ? null : ['pricing' => ['base_unit_gross' => $grossSnapshot]];
        $order->items()->create([
            'sku' => 'ITEM-1', 'name' => 'Test item', 'quantity' => 1,
            'unit_price' => $net, 'discount_amount' => 0, 'tax_rate' => $rate,
            'tax_amount' => $tax, 'line_total' => $net, 'payload' => $payload,
        ]);

        $preview = app(KiposOrderService::class)->preview($order);

        $this->assertSame($expectedPrice, data_get($preview, 'request.stavke.0.CIJENA'));
        $this->assertSame(number_format($net + $tax, 2, '.', ''), data_get($preview, 'request.stavke.0.IZNOS'));
        $this->assertSame('0.00', data_get($preview, 'request.stavke.0.RABAT'));
        $this->assertSame(number_format($net + $tax, 2, '.', ''), data_get($preview, 'request.narudzba.IZNOS_UKUPNO'));
        $this->assertSame([], $preview['warnings']);
        Http::assertNothingSent();
    }

    public static function itemPrices(): array
    {
        return [
            'DE preserves 9.99 rather than 9.98 from rounded net' => ['DE', 19.0, 8.39, 1.60, 9.99, '9.99'],
            'AT preserves 9.99 rather than 10.00 from rounded net' => ['AT', 20.0, 8.33, 1.66, '9.99', '9.99'],
            'historical DE unchanged' => ['DE', 19.0, 7.99, 1.52, null, '9.51'],
            'historical AT unchanged' => ['AT', 20.0, 7.99, 1.60, null, '9.59'],
            'invalid DE snapshot falls back' => ['DE', 19.0, 7.99, 1.52, 'invalid', '9.51'],
            'invalid AT snapshot falls back' => ['AT', 20.0, 7.99, 1.60, 'invalid', '9.59'],
        ];
    }

    public function test_snapshot_is_the_original_unit_price_while_discount_and_quantity_still_determine_line_amount(): void
    {
        $order = $this->order('DE', 17.98);
        $order->items()->create([
            'sku' => 'ITEM-1', 'name' => 'Discounted item', 'quantity' => 2,
            'unit_price' => 8.39, 'discount_amount' => 1.68, 'tax_rate' => 19,
            'tax_amount' => 2.88, 'line_total' => 15.10,
            'payload' => ['pricing' => ['base_unit_gross' => 9.99, 'unit_gross' => 8.99]],
        ]);

        $preview = app(KiposOrderService::class)->preview($order);

        $this->assertSame('9.99', data_get($preview, 'request.stavke.0.CIJENA'));
        $this->assertSame('2', data_get($preview, 'request.stavke.0.KOLICINA'));
        $this->assertSame('10.01', data_get($preview, 'request.stavke.0.RABAT'));
        $this->assertSame('17.98', data_get($preview, 'request.stavke.0.IZNOS'));
        $this->assertSame('17.98', data_get($preview, 'request.narudzba.IZNOS_UKUPNO'));
        $this->assertSame([], $preview['warnings']);
        Http::assertNothingSent();
    }

    private function order(string $country, float $grandTotal): Order
    {
        return Order::query()->create([
            'order_number' => 'GROSS-SNAPSHOT', 'customer_name' => 'Test Customer',
            'customer_email' => 'customer@example.test', 'source' => 'web', 'locale' => 'de',
            'currency_code' => 'EUR', 'currency_rate' => 1, 'placed_at' => now(),
            'billing_country_code' => $country, 'shipping_country_code' => $country,
            'shipping_total' => 0, 'payment_fee_total' => 0, 'grand_total' => $grandTotal,
        ]);
    }
}
