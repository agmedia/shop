<?php

namespace App\Observers\Sales;

use App\Jobs\SendKlaviyoPlacedOrderJob;
use App\Models\Sales\Order\Order;
use App\Services\Integrations\Klaviyo\KlaviyoOrderService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly KlaviyoOrderService $klaviyo) {}

    public function created(Order $order): void
    {
        $this->dispatchPlacedOrder($order);
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged('status_id') && $order->source === 'web') {
            $status = \App\Models\Settings\Local\OrderStatus::query()->find($order->status_id);
            if ($status && (data_get($status->settings, 'is_shipped') === true || in_array(strtolower($status->code), ['sent', 'shipped', 'dispatched'], true))) {
                \App\Jobs\SendOrderDispatchConfirmation::dispatch((int) $order->id)->onConnection('database');
            }
        }

        if ($order->wasChanged('paid_at') && $order->paid_at !== null) {
            $this->dispatchPlacedOrder($order);
        }
    }

    private function dispatchPlacedOrder(Order $order): void
    {
        try {
            app(SystemSettingsService::class)->refresh();
            if ($this->klaviyo->enabled() && $this->klaviyo->eligible($order)) {
                Bus::dispatch(new SendKlaviyoPlacedOrderJob((int) $order->id));
            }
        } catch (Throwable) {
            // An unavailable integration must never prevent checkout or payment confirmation.
            Log::warning('Could not dispatch Klaviyo order event.', ['order_id' => (int) $order->id]);
        }
    }
}
