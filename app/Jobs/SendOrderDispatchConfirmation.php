<?php

namespace App\Jobs;

use App\Models\Sales\Order\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendOrderDispatchConfirmation implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $orderId) {}

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(): void
    {
        DB::transaction(function (): void {
            $order = Order::query()->lockForUpdate()->findOrFail($this->orderId);
            if (data_get($order->payload, 'dispatch_confirmation.sent_at') || ! filter_var($order->customer_email, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $order->loadMissing('items');
            Mail::to($order->customer_email)->send((new \App\Mail\OrderDispatchConfirmation($order))->locale($order->locale ?: 'en'));
            $payload = $order->payload ?? [];
            $payload['dispatch_confirmation']['sent_at'] = now()->toIso8601String();
            $order->forceFill(['payload' => $payload])->saveQuietly();
        });
    }
}
