<?php

namespace App\Jobs;

use App\Models\Sales\Order\Order;
use App\Services\Integrations\Klaviyo\KlaviyoOrderService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendKlaviyoPlacedOrderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(public readonly int $orderId) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(KlaviyoOrderService $klaviyo): void
    {
        // Queue workers retain singleton services between jobs; re-read settings changed by the admin.
        app(SystemSettingsService::class)->refresh();

        $order = Order::query()->with(['items', 'status'])->find($this->orderId);

        if ($order) {
            $klaviyo->track($order);
        }
    }
}
