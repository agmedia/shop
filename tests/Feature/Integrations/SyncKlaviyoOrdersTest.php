<?php

namespace Tests\Feature\Integrations;

use App\Jobs\SendKlaviyoPlacedOrderJob;
use App\Models\Sales\Order\Order;
use App\Models\Settings\Local\OrderStatus;
use App\Services\Integrations\Klaviyo\KlaviyoOrderService;
use App\Services\Settings\SystemSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

class SyncKlaviyoOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake(['https://a.klaviyo.com/api/events/' => Http::response([], 202)]);
    }

    public function test_dry_run_counts_eligible_orders_without_configuration_or_queued_events(): void
    {
        $this->configureIntegration('none', '');
        $this->historicalOrder();
        $this->historicalOrder(['payment_method_code' => 'corvuspay', 'paid_at' => '2026-07-02 10:00:00']);
        $this->historicalOrder(['payment_method_code' => 'corvuspay']);
        $this->historicalOrder(['customer_email' => 'not-an-email']);
        $this->historicalOrder(['status_id' => $this->cancelledStatus()->id]);
        $this->historicalOrder(['source' => 'admin']);
        $this->historicalOrder(['placed_at' => null]);

        $this->artisan('klaviyo:sync-orders', ['--dry-run' => true])
            ->expectsOutput('Eligible: 2; skipped: 3.')
            ->assertExitCode(Command::SUCCESS);

        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_live_import_queues_only_eligible_web_orders_and_sends_their_original_dates(): void
    {
        $this->configureIntegration();
        $bankOrder = $this->historicalOrder(['placed_at' => '2026-06-18 09:15:00']);
        $cashOrder = $this->historicalOrder(['payment_method_code' => 'cod', 'placed_at' => '2026-06-20 14:25:00']);
        $cardOrder = $this->historicalOrder([
            'payment_method_code' => 'corvuspay',
            'placed_at' => '2026-06-22 16:35:00',
            'paid_at' => '2026-06-22 16:40:00',
        ]);
        $this->historicalOrder(['payment_method_code' => 'corvuspay']);
        $this->historicalOrder(['customer_email' => 'invalid-email']);
        $this->historicalOrder(['status_id' => $this->cancelledStatus()->id]);
        $this->historicalOrder(['source' => 'admin']);
        $this->historicalOrder(['placed_at' => null]);
        $eligibleOrders = collect([$bankOrder, $cashOrder, $cardOrder]);

        $this->artisan('klaviyo:sync-orders')
            ->expectsOutput('Queued: 3; skipped: 3.')
            ->assertExitCode(Command::SUCCESS);

        Queue::assertPushed(SendKlaviyoPlacedOrderJob::class, 3);
        $jobs = Queue::pushed(SendKlaviyoPlacedOrderJob::class);
        $this->assertEqualsCanonicalizing($eligibleOrders->pluck('id')->all(), $jobs->pluck('orderId')->all());
        Http::assertNothingSent();

        foreach ($jobs as $job) {
            $job->handle(app(KlaviyoOrderService::class));
        }

        Http::assertSentCount(3);
        foreach ($eligibleOrders as $order) {
            Http::assertSent(fn ($request): bool => $request['data']['attributes']['unique_id'] === 'web-order-'.$order->order_number
                && $request['data']['attributes']['time'] === $order->placed_at->toIso8601String()
            );
            $this->assertTrue($order->fresh()->placed_at->equalTo($order->placed_at));
        }
    }

    public function test_date_range_includes_both_boundary_days_and_excludes_neighboring_days(): void
    {
        $this->configureIntegration();
        $this->historicalOrder(['placed_at' => '2026-07-04 23:59:59']);
        $first = $this->historicalOrder(['placed_at' => '2026-07-05 00:00:00']);
        $middle = $this->historicalOrder(['placed_at' => '2026-07-06 12:00:00']);
        $last = $this->historicalOrder(['placed_at' => '2026-07-07 23:59:59']);
        $this->historicalOrder(['placed_at' => '2026-07-08 00:00:00']);

        $this->artisan('klaviyo:sync-orders', ['--from' => '2026-07-05', '--to' => '2026-07-07'])
            ->expectsOutput('Queued: 3; skipped: 0.')
            ->assertExitCode(Command::SUCCESS);

        $this->assertEqualsCanonicalizing(
            [$first->id, $middle->id, $last->id],
            Queue::pushed(SendKlaviyoPlacedOrderJob::class)->pluck('orderId')->all()
        );
    }

    public function test_historical_sync_skips_orders_without_explicit_saved_marketing_consent(): void
    {
        $this->configureIntegration();
        $consented = $this->historicalOrder();
        $this->historicalOrder(['payload' => []]);
        $this->historicalOrder(['payload' => ['privacy' => ['marketing_consent' => false]]]);
        $this->historicalOrder(['payload' => ['privacy' => ['marketing_consent' => 'true']]]);

        $this->artisan('klaviyo:sync-orders')
            ->expectsOutput('Queued: 1; skipped: 3.')
            ->assertExitCode(Command::SUCCESS);

        Queue::assertPushed(SendKlaviyoPlacedOrderJob::class, 1);
        Queue::assertPushed(SendKlaviyoPlacedOrderJob::class, fn ($job): bool => $job->orderId === $consented->id);
    }

    #[DataProvider('oneSidedDateRanges')]
    public function test_date_range_can_use_only_one_bound(array $options, array $expectedOrderIndexes): void
    {
        $this->configureIntegration();
        $orders = collect([
            $this->historicalOrder(['placed_at' => '2026-07-04 23:59:59']),
            $this->historicalOrder(['placed_at' => '2026-07-05 00:00:00']),
            $this->historicalOrder(['placed_at' => '2026-07-05 23:59:59']),
            $this->historicalOrder(['placed_at' => '2026-07-06 00:00:00']),
        ]);

        $this->artisan('klaviyo:sync-orders', $options)->assertExitCode(Command::SUCCESS);

        $this->assertEqualsCanonicalizing(
            $orders->only($expectedOrderIndexes)->pluck('id')->all(),
            Queue::pushed(SendKlaviyoPlacedOrderJob::class)->pluck('orderId')->all()
        );
    }

    public static function oneSidedDateRanges(): array
    {
        return [
            'from only' => [['--from' => '2026-07-05'], [1, 2, 3]],
            'to only' => [['--to' => '2026-07-05'], [0, 1, 2]],
            'same day' => [['--from' => '2026-07-05', '--to' => '2026-07-05'], [1, 2]],
        ];
    }

    #[DataProvider('invalidDateRanges')]
    public function test_invalid_or_inverted_dates_fail_without_queuing_orders(array $options): void
    {
        $this->configureIntegration();
        $this->historicalOrder();

        $this->artisan('klaviyo:sync-orders', $options)->assertExitCode(Command::FAILURE);

        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public static function invalidDateRanges(): array
    {
        return [
            'natural language' => [['--from' => 'yesterday']],
            'wrong format' => [['--from' => '05/07/2026']],
            'impossible date' => [['--from' => '2026-02-30']],
            'invalid upper bound' => [['--to' => '2026-13-01']],
            'inverted range' => [['--from' => '2026-07-08', '--to' => '2026-07-05']],
        ];
    }

    #[DataProvider('unconfiguredIntegrations')]
    public function test_live_import_fails_when_integration_is_not_configured(string $provider, string $apiKey): void
    {
        $this->configureIntegration($provider, $apiKey);
        $this->historicalOrder();

        $this->artisan('klaviyo:sync-orders')
            ->expectsOutputToContain('configure a private API key')
            ->assertExitCode(Command::FAILURE);

        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public static function unconfiguredIntegrations(): array
    {
        return [
            'provider disabled' => ['none', 'private-test-key'],
            'different provider' => ['mailchimp', 'private-test-key'],
            'key missing' => ['klaviyo', ''],
        ];
    }

    private function configureIntegration(string $provider = 'klaviyo', string $apiKey = 'private-test-key'): void
    {
        app(SystemSettingsService::class)->putMany([
            'store_newsletter_provider' => $provider,
            'store_newsletter_klaviyo_api_key' => $apiKey,
        ]);
    }

    private function historicalOrder(array $attributes = []): Order
    {
        return Order::withoutEvents(fn (): Order => Order::query()->create(array_merge([
            'order_number' => 'HISTORY-'.str()->uuid(),
            'source' => 'web',
            'customer_name' => 'Historical Customer',
            'customer_email' => 'history@example.test',
            'payment_method_code' => 'bank',
            'currency_code' => 'EUR',
            'grand_total' => 50,
            'placed_at' => '2026-07-01 10:00:00',
            'payload' => ['privacy' => [
                'marketing_consent' => true,
                'marketing_consent_at' => CarbonImmutable::parse($attributes['placed_at'] ?? '2026-07-01 10:00:00')->toIso8601String(),
            ]],
        ], $attributes)));
    }

    private function cancelledStatus(): OrderStatus
    {
        return OrderStatus::query()->create([
            'code' => 'cancelled',
            'name' => 'Cancelled',
            'is_cancelled' => true,
            'is_active' => true,
        ]);
    }
}
