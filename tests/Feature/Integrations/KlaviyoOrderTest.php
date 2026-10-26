<?php

namespace Tests\Feature\Integrations;

use App\Jobs\SendKlaviyoPlacedOrderJob;
use App\Models\Sales\Order\Order;
use App\Models\Settings\Local\OrderStatus;
use App\Models\Settings\System\SystemSetting;
use App\Services\Integrations\Klaviyo\KlaviyoOrderService;
use App\Services\Settings\SystemSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class KlaviyoOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        app(SystemSettingsService::class)->putMany([
            'store_newsletter_provider' => 'klaviyo',
            'store_newsletter_klaviyo_api_key' => 'test-private-key',
            'store_newsletter_klaviyo_list_id' => '',
        ]);
    }

    public function test_order_event_contains_profile_line_items_and_stable_identity(): void
    {
        Http::fake(['a.klaviyo.com/api/events/' => Http::response([], 202)]);
        $order = $this->createOrder([
            'customer_email' => ' Customer@Example.com ',
            'billing_first_name' => 'Ana',
            'billing_last_name' => 'Anić',
            'subtotal' => 100,
            'discount_total' => 10,
            'shipping_total' => 5,
            'grand_total' => 95,
            'payload' => ['coupon_code' => 'BALI10', 'privacy' => [
                'marketing_consent' => true,
                'marketing_consent_at' => now()->startOfSecond()->toIso8601String(),
            ]],
        ]);
        $this->createItem($order, ['sku' => 'BALI-M', 'name' => 'Bali haljina', 'unit_price' => 50, 'quantity' => 2, 'line_total' => 90]);

        $service = app(KlaviyoOrderService::class);
        $service->track($order);
        $service->track($order->fresh());

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request) use ($order): bool {
            $attributes = $request['data']['attributes'];

            $this->assertSame('https://a.klaviyo.com/api/events/', $request->url());
            $this->assertTrue($request->hasHeader('Authorization', 'Klaviyo-API-Key test-private-key'));
            $this->assertTrue($request->hasHeader('revision', '2026-07-15'));
            $this->assertSame('event', $request['data']['type']);
            $this->assertSame('Placed Order', data_get($attributes, 'metric.data.attributes.name'));
            $this->assertSame('customer@example.com', data_get($attributes, 'profile.data.attributes.email'));
            $this->assertSame('Ana', data_get($attributes, 'profile.data.attributes.first_name'));
            $this->assertSame('Anić', data_get($attributes, 'profile.data.attributes.last_name'));
            $this->assertSame('web-order-BALI-TEST-1', $attributes['unique_id']);
            $this->assertSame($order->placed_at->toIso8601String(), $attributes['time']);
            $this->assertSame(95.0, (float) $attributes['value']);
            $this->assertIsNumeric($attributes['value']);
            $this->assertSame('EUR', $attributes['value_currency']);
            $this->assertSame([
                'OrderId' => (string) $order->id,
                'OrderNumber' => 'BALI-TEST-1',
                'ItemNames' => ['Bali haljina'],
                'Items' => [[
                    'ProductID' => 'BALI',
                    'SKU' => 'BALI-M',
                    'ProductName' => 'Bali haljina',
                    'Quantity' => 2,
                    'ItemPrice' => 50.0,
                    'RowTotal' => 90.0,
                ]],
                'DiscountCode' => 'BALI10',
                'DiscountValue' => 10.0,
                'ShippingCost' => 5.0,
                'PaymentMethod' => 'Bankovna uplata',
                'ShippingMethod' => 'Dostava DPD',
            ], $attributes['properties']);

            return true;
        });

        $ids = Http::recorded()->map(fn (array $record) => $record[0]['data']['attributes']['unique_id'])->all();
        $this->assertSame(['web-order-BALI-TEST-1', 'web-order-BALI-TEST-1'], $ids);
    }

    public function test_events_require_klaviyo_and_private_key_but_no_newsletter_list(): void
    {
        Http::fake(['a.klaviyo.com/api/events/' => Http::response([], 202)]);
        $order = $this->createOrder();
        $service = app(KlaviyoOrderService::class);
        $this->assertTrue($service->enabled());

        app(SystemSettingsService::class)->put('store_newsletter_provider', 'mailchimp');
        $this->assertFalse($service->enabled());
        $service->track($order);

        app(SystemSettingsService::class)->putMany([
            'store_newsletter_provider' => 'klaviyo',
            'store_newsletter_klaviyo_api_key' => ' ',
        ]);
        $this->assertFalse($service->enabled());
        $service->track($order);
        Http::assertNothingSent();
    }

    public function test_unpaid_online_payment_aliases_wait_for_confirmed_payment(): void
    {
        $service = app(KlaviyoOrderService::class);

        foreach (['wspay', 'ws_pay', 'corvus', 'corvuspay', 'corvus_pay', 'keks', 'keks_pay', 'kekspay'] as $index => $code) {
            $order = $this->createOrder(['order_number' => 'CARD-'.$index, 'payment_method_code' => $code]);
            $this->assertFalse($service->eligible($order), $code);
            $order->paid_at = now();
            $this->assertTrue($service->eligible($order), $code);
        }
    }

    public function test_offline_orders_are_eligible_before_payment(): void
    {
        Http::fake(['a.klaviyo.com/api/events/' => Http::response([], 202)]);
        $service = app(KlaviyoOrderService::class);

        foreach (['bank', 'bank_transfer', 'cod', 'cash_on_delivery'] as $index => $code) {
            $order = $this->createOrder(['order_number' => 'OFFLINE-'.$index, 'payment_method_code' => $code]);
            $this->assertTrue($service->eligible($order));
            $service->track($order);
        }

        Http::assertSentCount(4);
    }

    public function test_missing_denied_or_unproven_marketing_consent_never_dispatches_or_sends(): void
    {
        Bus::fake();
        Http::fake();
        $service = app(KlaviyoOrderService::class);

        foreach ([
            [],
            ['privacy' => ['marketing_consent' => false, 'marketing_consent_at' => now()->toIso8601String()]],
            ['privacy' => ['marketing_consent' => 'true', 'marketing_consent_at' => now()->toIso8601String()]],
            ['privacy' => ['marketing_consent' => true]],
            ['privacy' => ['marketing_consent' => true, 'marketing_consent_at' => 'invalid']],
            ['privacy' => ['marketing_consent' => true, 'marketing_consent_at' => now()->addDay()->toIso8601String()]],
            ['privacy' => ['marketing_consent' => true, 'marketing_consent_at' => now()->subDays(183)->toIso8601String()]],
        ] as $index => $payload) {
            $order = Order::query()->create($this->orderAttributes([
                'order_number' => 'NO-CONSENT-'.$index,
                'payment_method_code' => 'corvuspay',
                'payload' => $payload,
            ]));
            $order->update(['paid_at' => now()]);
            $this->assertFalse($service->eligible($order));
            $service->track($order);
            (new SendKlaviyoPlacedOrderJob((int) $order->id))->handle($service);
        }

        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public function test_invalid_imported_unplaced_cancelled_and_failed_orders_are_skipped(): void
    {
        Http::fake();
        $service = app(KlaviyoOrderService::class);

        foreach ([['source' => 'import'], ['customer_email' => 'invalid'], ['placed_at' => null]] as $index => $attributes) {
            $order = $this->createOrder(['order_number' => 'INVALID-'.$index, ...$attributes]);
            $this->assertFalse($service->eligible($order));
            $service->track($order);
        }

        foreach (['cancelled', 'failed', 'custom-cancelled'] as $index => $code) {
            $status = OrderStatus::query()->create([
                'code' => $code,
                'name' => $code,
                'is_cancelled' => $code === 'custom-cancelled',
            ]);
            $order = $this->createOrder(['order_number' => 'CANCELLED-'.$index, 'status_id' => $status->id, 'paid_at' => now()]);
            $this->assertFalse($service->eligible($order));
            $service->track($order);
        }

        Http::assertNothingSent();
    }

    public function test_observer_waits_until_commit_and_job_reads_all_items(): void
    {
        Http::fake(['a.klaviyo.com/api/events/' => Http::response([], 202)]);

        DB::transaction(function (): void {
            $order = Order::query()->create($this->orderAttributes());
            $this->createItem($order, ['name' => 'Prva stavka']);
            $this->createItem($order, ['name' => 'Druga stavka']);

            Http::assertNothingSent();
        });

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request['data']['attributes']['properties']['ItemNames'] === ['Prva stavka', 'Druga stavka']
            && count($request['data']['attributes']['properties']['Items']) === 2);
    }

    public function test_rolled_back_order_never_dispatches_an_event(): void
    {
        Bus::fake();

        DB::beginTransaction();
        $order = Order::query()->create($this->orderAttributes());
        $this->createItem($order);
        Bus::assertNothingDispatched();
        DB::rollBack();

        Bus::assertNothingDispatched();
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_confirmed_online_payment_dispatches_after_commit_only_when_paid_at_changes(): void
    {
        Bus::fake();
        $order = Order::query()->create($this->orderAttributes(['payment_method_code' => 'corvuspay']));
        $order->update(['admin_note' => 'Still waiting for payment']);
        Bus::assertNothingDispatched();

        DB::transaction(function () use ($order): void {
            $order->update(['paid_at' => now()]);
            Bus::assertNothingDispatched();
        });

        Bus::assertDispatched(SendKlaviyoPlacedOrderJob::class, fn ($job): bool => $job->orderId === $order->id);
        $order->update(['admin_note' => 'Payment confirmed']);
        $order->update(['paid_at' => null]);
        Bus::assertDispatchedTimes(SendKlaviyoPlacedOrderJob::class, 1);
    }

    public function test_dispatch_failure_does_not_fail_order_creation_or_log_secrets(): void
    {
        Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Private provider failure: test-private-key'));
        Log::shouldReceive('warning')->once()->with('Could not dispatch Klaviyo order event.', ['order_id' => 1]);

        $order = DB::transaction(fn (): Order => Order::query()->create($this->orderAttributes()));

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_number' => 'BALI-TEST-1']);
    }

    public function test_provider_failure_in_sync_queue_does_not_fail_checkout(): void
    {
        Http::fake(['a.klaviyo.com/api/events/' => Http::response(['errors' => [['detail' => 'Private provider details']]], 503)]);
        Log::shouldReceive('warning')->once()->with('Could not dispatch Klaviyo order event.', ['order_id' => 1]);

        DB::transaction(function (): void {
            $order = Order::query()->create($this->orderAttributes());
            $this->createItem($order);
        });

        $this->assertDatabaseHas('orders', ['order_number' => 'BALI-TEST-1']);
        $this->assertDatabaseCount('order_items', 1);
        Http::assertSentCount(1);
    }

    public function test_job_propagates_provider_failures_for_queue_retry(): void
    {
        Http::fake(['a.klaviyo.com/api/events/' => Http::response(['detail' => 'Private data'], 429)]);
        $order = $this->createOrder();
        $job = new SendKlaviyoPlacedOrderJob((int) $order->id);
        $this->assertSame(5, $job->tries);
        $this->assertSame([60, 300, 900, 3600], $job->backoff());
        $this->assertNull($job->queue);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Klaviyo order event failed (HTTP 429).');
        $job->handle(app(KlaviyoOrderService::class));
    }

    public function test_job_propagates_connection_failures_for_queue_retry(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection unavailable.'));
        $order = $this->createOrder();

        $this->expectException(ConnectionException::class);
        (new SendKlaviyoPlacedOrderJob((int) $order->id))->handle(app(KlaviyoOrderService::class));
    }

    public function test_job_uses_current_order_state_and_skips_deleted_orders(): void
    {
        Http::fake();
        $order = $this->createOrder();
        $job = new SendKlaviyoPlacedOrderJob((int) $order->id);
        $order->updateQuietly(['payment_method_code' => 'wspay']);
        $job->handle(app(KlaviyoOrderService::class));
        $order->deleteQuietly();
        $job->handle(app(KlaviyoOrderService::class));

        Http::assertNothingSent();
    }

    public function test_worker_refreshes_provider_and_rotated_key_changed_by_another_process(): void
    {
        Http::fake(['a.klaviyo.com/api/events/' => Http::response([], 202)]);
        $order = $this->createOrder();
        $settings = app(SystemSettingsService::class);
        $settings->put('store_newsletter_provider', 'none');
        $this->assertSame('none', $settings->get('store_newsletter_provider'));
        $this->assertSame('test-private-key', $settings->get('store_newsletter_klaviyo_api_key'));

        // Simulate admin writes in a separate process: update the database and shared cache only.
        SystemSetting::query()->where('key', 'store_newsletter_provider')->update(['value' => json_encode('klaviyo')]);
        SystemSetting::query()->where('key', 'store_newsletter_klaviyo_api_key')->update(['value' => json_encode('rotated-private-key')]);
        Cache::forget('settings.system.map');
        $this->assertSame('none', $settings->get('store_newsletter_provider'));

        $job = new SendKlaviyoPlacedOrderJob((int) $order->id);
        $job->handle(app(KlaviyoOrderService::class));

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Klaviyo-API-Key rotated-private-key'));

        SystemSetting::query()->where('key', 'store_newsletter_provider')->update(['value' => json_encode('none')]);
        Cache::forget('settings.system.map');
        $this->assertSame('klaviyo', $settings->get('store_newsletter_provider'));

        $job->handle(app(KlaviyoOrderService::class));

        Http::assertSentCount(1);
        $this->assertSame('none', $settings->get('store_newsletter_provider'));
    }

    private function createOrder(array $attributes = []): Order
    {
        return Order::withoutEvents(fn (): Order => Order::query()->create($this->orderAttributes($attributes)));
    }

    private function orderAttributes(array $attributes = []): array
    {
        return [
            'order_number' => 'BALI-TEST-1',
            'source' => 'web',
            'customer_name' => 'Test Customer',
            'customer_email' => 'customer@example.com',
            'payment_method_code' => 'bank_transfer',
            'payment_method_name' => 'Bankovna uplata',
            'shipping_method_code' => 'dpd',
            'shipping_method_name' => 'Dostava DPD',
            'currency_code' => 'EUR',
            'grand_total' => 50,
            'placed_at' => now()->startOfSecond(),
            'payload' => ['privacy' => [
                'marketing_consent' => true,
                'marketing_consent_at' => CarbonImmutable::parse($attributes['placed_at'] ?? now())->toIso8601String(),
            ]],
            ...$attributes,
        ];
    }

    private function createItem(Order $order, array $attributes = []): void
    {
        $order->items()->create([
            'sku' => 'BALI-M',
            'code' => 'BALI',
            'name' => 'Bali haljina',
            'unit_price' => 50,
            'quantity' => 1,
            'line_total' => 50,
            ...$attributes,
        ]);
    }
}
