<?php

namespace App\Console\Commands;

use App\Jobs\SendKlaviyoPlacedOrderJob;
use App\Models\Sales\Order\Order;
use App\Services\Integrations\Klaviyo\KlaviyoOrderService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class SyncKlaviyoOrders extends Command
{
    protected $signature = 'klaviyo:sync-orders
        {--from= : First order date, YYYY-MM-DD (inclusive)}
        {--to= : Last order date, YYYY-MM-DD (inclusive)}
        {--dry-run : Count eligible orders without sending or queuing events}';

    protected $description = 'Sync historical web orders to Klaviyo using their original dates and stable event IDs';

    public function handle(KlaviyoOrderService $klaviyo): int
    {
        $dates = ['from' => $this->option('from'), 'to' => $this->option('to')];
        $rules = [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ];
        if ($dates['from']) {
            $rules['to'][] = 'after_or_equal:from';
        }
        $validator = Validator::make($dates, $rules);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        if (! $this->option('dry-run') && ! $klaviyo->enabled()) {
            $this->error('Select Klaviyo as the newsletter provider and configure a private API key with Events write access first.');

            return self::FAILURE;
        }

        $query = Order::query()->where('source', 'web')->whereNotNull('placed_at')->with('status');
        if ($dates['from']) {
            $query->where('placed_at', '>=', CarbonImmutable::parse($dates['from'])->startOfDay());
        }
        if ($dates['to']) {
            $query->where('placed_at', '<', CarbonImmutable::parse($dates['to'])->addDay()->startOfDay());
        }

        $eligible = 0;
        $skipped = 0;
        foreach ($query->lazyById(250) as $order) {
            if (! $klaviyo->eligible($order)) {
                $skipped++;

                continue;
            }

            if (! $this->option('dry-run')) {
                try {
                    SendKlaviyoPlacedOrderJob::dispatch((int) $order->id);
                } catch (\Throwable $exception) {
                    report($exception);
                    $this->error('Unable to queue order '.$order->order_number.'. Fix the queue connection and rerun the command.');

                    return self::FAILURE;
                }
            }
            $eligible++;
        }

        $this->info(($this->option('dry-run') ? 'Eligible' : 'Queued').': '.$eligible.'; skipped: '.$skipped.'.');

        return self::SUCCESS;
    }
}
