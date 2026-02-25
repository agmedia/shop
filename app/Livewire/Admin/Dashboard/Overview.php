<?php

namespace App\Livewire\Admin\Dashboard;

use App\Models\Catalog\Action\CatalogAction;
use App\Models\Catalog\Attribute\Attribute as CatalogAttribute;
use App\Models\Catalog\Category\Category;
use App\Models\Catalog\Manufacturer\Manufacturer;
use App\Models\Catalog\Option\Option;
use App\Models\Catalog\Product\Product;
use App\Models\Content\Blog\BlogPost;
use App\Models\Content\Page\InfoPage;
use App\Models\Sales\Order\Order;
use App\Models\Settings\Local\OrderStatus;
use App\Models\User;
use App\Models\User\LoyaltyTransaction;
use App\Models\User\UserTrackingEvent;
use App\Services\Catalog\CatalogFeatureService;
use App\Services\Settings\SystemSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

class Overview extends Component
{
    public string $rangeDays = '7';

    public function mount(): void
    {
        if (! in_array($this->rangeDays, ['1', '7', '30'], true)) {
            $this->rangeDays = '7';
        }
    }

    public function render()
    {
        $settings = app(SystemSettingsService::class);
        $catalogFeatures = app(CatalogFeatureService::class)->all();
        $loyaltyEnabled = (bool) $settings->get(
            'user_loyalty_enabled',
            (bool) config('user_features.flags.user_loyalty_enabled', true)
        );
        $trackingEnabled = (bool) $settings->get(
            'user_tracking_enabled',
            (bool) config('user_features.flags.user_tracking_enabled', true)
        );
        $storeCurrencyCode = strtoupper((string) $settings->get('store_schema_product_currency', 'EUR'));
        $storeCurrencySymbol = \App\Support\Currency::symbol($storeCurrencyCode);

        [$start, $end, $previousStart, $previousEnd, $days] = $this->resolveRangeWindow();

        $ordersCurrentQuery = $this->ordersInRange($start, $end);
        $ordersPreviousQuery = $this->ordersInRange($previousStart, $previousEnd);

        $ordersCurrentCount = (int) (clone $ordersCurrentQuery)->count();
        $ordersPreviousCount = (int) (clone $ordersPreviousQuery)->count();
        $revenueCurrent = (float) (clone $ordersCurrentQuery)->sum('grand_total');
        $revenuePrevious = (float) (clone $ordersPreviousQuery)->sum('grand_total');
        $itemsSoldCurrent = (int) (clone $ordersCurrentQuery)->sum('item_qty');
        $itemsSoldPrevious = (int) (clone $ordersPreviousQuery)->sum('item_qty');
        $aovCurrent = $ordersCurrentCount > 0 ? $revenueCurrent / $ordersCurrentCount : 0.0;
        $aovPrevious = $ordersPreviousCount > 0 ? $revenuePrevious / $ordersPreviousCount : 0.0;
        $avgItemsPerOrderCurrent = $ordersCurrentCount > 0 ? ($itemsSoldCurrent / $ordersCurrentCount) : 0.0;
        $avgItemsPerOrderPrevious = $ordersPreviousCount > 0 ? ($itemsSoldPrevious / $ordersPreviousCount) : 0.0;
        $paidOrdersCurrent = (int) Order::query()
            ->whereBetween('paid_at', [$start, $end])
            ->count();
        $paidOrdersPrevious = (int) Order::query()
            ->whereBetween('paid_at', [$previousStart, $previousEnd])
            ->count();
        $customersCurrentCount = (int) (clone $ordersCurrentQuery)
            ->whereNotNull('customer_email')
            ->distinct('customer_email')
            ->count('customer_email');
        $customersPreviousCount = (int) (clone $ordersPreviousQuery)
            ->whereNotNull('customer_email')
            ->distinct('customer_email')
            ->count('customer_email');

        $usersCurrentCount = User::query()
            ->whereBetween('created_at', [$start, $end])
            ->count();
        $usersPreviousCount = User::query()
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();
        $productsCurrentCount = (int) Product::query()
            ->whereBetween('created_at', [$start, $end])
            ->count();
        $productsPreviousCount = (int) Product::query()
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();

        $kpis = [
            [
                'label' => __('Orders'),
                'value' => number_format($ordersCurrentCount),
                'delta' => $this->formatDelta($ordersCurrentCount, $ordersPreviousCount),
            ],
            [
                'label' => __('Revenue'),
                'value' => number_format($revenueCurrent, 2),
                'suffix' => $storeCurrencySymbol,
                'delta' => $this->formatDelta($revenueCurrent, $revenuePrevious),
            ],
            [
                'label' => __('AOV'),
                'value' => number_format($aovCurrent, 2),
                'suffix' => $storeCurrencySymbol,
                'delta' => $this->formatDelta($aovCurrent, $aovPrevious),
            ],
            [
                'label' => __('New Users'),
                'value' => number_format($usersCurrentCount),
                'delta' => $this->formatDelta($usersCurrentCount, $usersPreviousCount),
            ],
            [
                'label' => __('Paid Orders'),
                'value' => number_format($paidOrdersCurrent),
                'delta' => $this->formatDelta($paidOrdersCurrent, $paidOrdersPrevious),
            ],
            [
                'label' => __('Items Sold'),
                'value' => number_format($itemsSoldCurrent),
                'delta' => $this->formatDelta($itemsSoldCurrent, $itemsSoldPrevious),
            ],
            [
                'label' => __('Customers Ordered'),
                'value' => number_format($customersCurrentCount),
                'delta' => $this->formatDelta($customersCurrentCount, $customersPreviousCount),
            ],
            [
                'label' => __('Avg Items / Order'),
                'value' => number_format($avgItemsPerOrderCurrent, 2),
                'delta' => $this->formatDelta($avgItemsPerOrderCurrent, $avgItemsPerOrderPrevious),
            ],
            [
                'label' => __('New Products'),
                'value' => number_format($productsCurrentCount),
                'delta' => $this->formatDelta($productsCurrentCount, $productsPreviousCount),
            ],
        ];

        if ($loyaltyEnabled) {
            $loyaltyCurrent = (int) LoyaltyTransaction::query()
                ->whereBetween('created_at', [$start, $end])
                ->sum('points');
            $loyaltyPrevious = (int) LoyaltyTransaction::query()
                ->whereBetween('created_at', [$previousStart, $previousEnd])
                ->sum('points');

            $kpis[] = [
                'label' => __('Loyalty Net Points'),
                'value' => number_format($loyaltyCurrent),
                'delta' => $this->formatDelta($loyaltyCurrent, $loyaltyPrevious),
            ];
        } else {
            $cancelledStatusIds = OrderStatus::query()
                ->where('is_cancelled', true)
                ->pluck('id')
                ->all();

            $openOrdersCurrentQuery = clone $ordersCurrentQuery;
            $openOrdersPreviousQuery = clone $ordersPreviousQuery;

            if ($cancelledStatusIds !== []) {
                $openOrdersCurrentQuery->whereNotIn('status_id', $cancelledStatusIds);
                $openOrdersPreviousQuery->whereNotIn('status_id', $cancelledStatusIds);
            }

            $openOrdersCurrent = (int) $openOrdersCurrentQuery->count();
            $openOrdersPrevious = (int) $openOrdersPreviousQuery->count();

            $kpis[] = [
                'label' => __('Open Orders'),
                'value' => number_format($openOrdersCurrent),
                'delta' => $this->formatDelta($openOrdersCurrent, $openOrdersPrevious),
            ];
        }

        $statusRows = OrderStatus::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'code', 'name', 'color']);

        $pipelineCounts = (clone $ordersCurrentQuery)
            ->selectRaw('status_id, COUNT(*) as total')
            ->groupBy('status_id')
            ->pluck('total', 'status_id');

        $pipeline = $statusRows->map(function (OrderStatus $status) use ($pipelineCounts, $start, $end): array {
            return [
                'id' => $status->id,
                'name' => $status->name,
                'code' => $status->code,
                'color' => $status->color,
                'count' => (int) ($pipelineCounts[$status->id] ?? 0),
                'url' => route('admin.orders', [
                    'status' => $status->id,
                    'dateFrom' => $start->toDateString(),
                    'dateTo' => $end->toDateString(),
                ]),
            ];
        });

        $recentOrders = Order::query()
            ->with('status:id,name,color')
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'order_number', 'status_id', 'customer_name', 'grand_total', 'currency_code', 'placed_at', 'created_at']);

        $recentAdminActivity = Activity::query()
            ->with('causer:id,name,email')
            ->where(function (Builder $query): void {
                $query->whereNull('log_name')
                    ->orWhere('log_name', '!=', 'loyalty');
            })
            ->latest('id')
            ->limit(8)
            ->get();

        $recentLoyaltyActivity = collect();
        if ($loyaltyEnabled) {
            $recentLoyaltyActivity = Activity::query()
                ->with('causer:id,name,email')
                ->where('log_name', 'loyalty')
                ->latest('id')
                ->limit(8)
                ->get();
        }

        $recentTrackingEvents = collect();
        if ($trackingEnabled) {
            $recentTrackingEvents = UserTrackingEvent::query()
                ->with('user:id,name,email')
                ->latest('occurred_at')
                ->limit(8)
                ->get(['id', 'user_id', 'event', 'url', 'occurred_at']);
        }

        $trendRows = $this->buildTrendRows((int) min($days, 30));
        $trendLabels = $trendRows
            ->map(fn (array $row): string => CarbonImmutable::parse((string) $row['date'])->format('M d'))
            ->values()
            ->all();
        $trendRevenue = $trendRows
            ->map(fn (array $row): float => (float) $row['revenue'])
            ->values()
            ->all();
        $trendOrders = $trendRows
            ->map(fn (array $row): int => (int) $row['orders'])
            ->values()
            ->all();

        $userTrendByDate = array_fill_keys(
            $trendRows->pluck('date')->map(fn ($date): string => (string) $date)->all(),
            0
        );

        $userTrendRows = User::query()
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at']);

        foreach ($userTrendRows as $userTrendRow) {
            $bucket = $userTrendRow->created_at?->toDateString();
            if (! $bucket || ! array_key_exists($bucket, $userTrendByDate)) {
                continue;
            }

            $userTrendByDate[$bucket]++;
        }

        $trendUsers = $trendRows
            ->map(fn (array $row): int => (int) ($userTrendByDate[(string) $row['date']] ?? 0))
            ->values()
            ->all();

        $catalogSnapshot = [
            [
                'label' => __('Categories'),
                'value' => Category::query()->count(),
                'url' => route('admin.categories'),
            ],
            [
                'label' => __('Products'),
                'value' => Product::query()->count(),
                'url' => route('admin.products'),
            ],
            [
                'label' => __('Active Products'),
                'value' => Product::query()->where('is_active', true)->count(),
                'url' => route('admin.products'),
            ],
            [
                'label' => __('Pages'),
                'value' => InfoPage::query()->count(),
                'url' => route('admin.content.pages.index'),
            ],
        ];

        if (($catalogFeatures['catalog_use_blog'] ?? false) === true) {
            $catalogSnapshot[] = [
                'label' => __('Blog Posts'),
                'value' => BlogPost::query()->count(),
                'url' => route('admin.content.blog.index'),
            ];
        }

        if (($catalogFeatures['catalog_use_manufacturers'] ?? false) === true) {
            $catalogSnapshot[] = [
                'label' => __('Manufacturers'),
                'value' => Manufacturer::query()->count(),
                'url' => route('admin.manufacturers'),
            ];
        }

        if (($catalogFeatures['catalog_use_options'] ?? false) === true) {
            $catalogSnapshot[] = [
                'label' => __('Options'),
                'value' => Option::query()->count(),
                'url' => route('admin.options'),
            ];
        }

        if (($catalogFeatures['catalog_use_attributes'] ?? false) === true) {
            $catalogSnapshot[] = [
                'label' => __('Attributes'),
                'value' => CatalogAttribute::query()->count(),
                'url' => route('admin.attributes'),
            ];
        }

        if (($catalogFeatures['catalog_use_actions'] ?? false) === true) {
            $catalogSnapshot[] = [
                'label' => __('Actions'),
                'value' => CatalogAction::query()->count(),
                'url' => route('admin.actions'),
            ];
        }

        $statusChartColorMap = [
            'blue' => '#3b82f6',
            'emerald' => '#10b981',
            'green' => '#10b981',
            'rose' => '#f43f5e',
            'red' => '#f43f5e',
            'amber' => '#f59e0b',
            'yellow' => '#f59e0b',
            'violet' => '#8b5cf6',
            'purple' => '#8b5cf6',
            'cyan' => '#06b6d4',
            'gray' => '#94a3b8',
            'slate' => '#94a3b8',
        ];

        $pipelineLabels = $pipeline->pluck('name')->values()->all();
        $pipelineValues = $pipeline->pluck('count')->map(fn ($value): int => (int) $value)->values()->all();
        $pipelineColors = $pipeline
            ->map(function (array $status) use ($statusChartColorMap): string {
                $key = strtolower((string) ($status['color'] ?? 'slate'));

                return $statusChartColorMap[$key] ?? $statusChartColorMap['slate'];
            })
            ->values()
            ->all();

        $dashboardCharts = [
            'sales_trend' => [
                'type' => 'bar',
                'data' => [
                    'labels' => $trendLabels,
                    'datasets' => [
                        [
                            'label' => __('Revenue (:currency)', ['currency' => $storeCurrencySymbol]),
                            'data' => $trendRevenue,
                            'yAxisID' => 'yRevenue',
                            'backgroundColor' => 'rgba(14, 116, 144, 0.28)',
                            'borderColor' => '#0e7490',
                            'borderWidth' => 1,
                            'order' => 2,
                        ],
                        [
                            'type' => 'line',
                            'label' => __('Orders'),
                            'data' => $trendOrders,
                            'yAxisID' => 'yOrders',
                            'borderColor' => '#0f172a',
                            'backgroundColor' => 'rgba(15, 23, 42, 0.16)',
                            'pointBackgroundColor' => '#0f172a',
                            'pointRadius' => 2.5,
                            'tension' => 0.35,
                            'fill' => false,
                            'borderWidth' => 2,
                            'order' => 1,
                        ],
                    ],
                ],
                'options' => [
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'plugins' => [
                        'legend' => ['position' => 'top'],
                    ],
                    'scales' => [
                        'yRevenue' => ['position' => 'left', 'beginAtZero' => true],
                        'yOrders' => ['position' => 'right', 'beginAtZero' => true, 'grid' => ['drawOnChartArea' => false]],
                    ],
                ],
            ],
            'new_users_trend' => [
                'type' => 'line',
                'data' => [
                    'labels' => $trendLabels,
                    'datasets' => [
                        [
                            'label' => __('New Users'),
                            'data' => $trendUsers,
                            'borderColor' => '#0891b2',
                            'backgroundColor' => 'rgba(8, 145, 178, 0.15)',
                            'pointBackgroundColor' => '#0891b2',
                            'pointRadius' => 2.5,
                            'tension' => 0.35,
                            'fill' => true,
                            'borderWidth' => 2,
                        ],
                    ],
                ],
                'options' => [
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'plugins' => [
                        'legend' => ['position' => 'top'],
                    ],
                    'scales' => [
                        'y' => ['beginAtZero' => true],
                    ],
                ],
            ],
            'pipeline_share' => [
                'type' => 'doughnut',
                'data' => [
                    'labels' => $pipelineLabels,
                    'datasets' => [
                        [
                            'label' => __('Orders by Status'),
                            'data' => $pipelineValues,
                            'backgroundColor' => $pipelineColors,
                            'borderColor' => '#ffffff',
                            'borderWidth' => 2,
                        ],
                    ],
                ],
                'options' => [
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'plugins' => [
                        'legend' => ['position' => 'bottom'],
                    ],
                ],
            ],
        ];

        $featureFlags = [
            __('User Tracking') => $trackingEnabled,
            __('User Loyalty') => $loyaltyEnabled,
            __('API') => (bool) ($catalogFeatures['catalog_use_api'] ?? false),
            __('Luceed API') => (bool) ($catalogFeatures['catalog_use_luceed_api'] ?? false),
            __('Blog') => (bool) ($catalogFeatures['catalog_use_blog'] ?? false),
            __('Attributes') => (bool) ($catalogFeatures['catalog_use_attributes'] ?? false),
            __('Options') => (bool) ($catalogFeatures['catalog_use_options'] ?? false),
            __('Manufacturers') => (bool) ($catalogFeatures['catalog_use_manufacturers'] ?? false),
            __('Actions') => (bool) ($catalogFeatures['catalog_use_actions'] ?? false),
            __('Mobile PWA View') => (bool) ($catalogFeatures['catalog_use_mobile_pwa'] ?? false),
        ];

        return view('livewire.admin.dashboard.overview', [
            'start' => $start,
            'end' => $end,
            'days' => $days,
            'kpis' => $kpis,
            'pipeline' => $pipeline,
            'recentOrders' => $recentOrders,
            'recentAdminActivity' => $recentAdminActivity,
            'recentLoyaltyActivity' => $recentLoyaltyActivity,
            'recentTrackingEvents' => $recentTrackingEvents,
            'trackingEnabled' => $trackingEnabled,
            'loyaltyEnabled' => $loyaltyEnabled,
            'trendRows' => $trendRows,
            'catalogSnapshot' => $catalogSnapshot,
            'featureFlags' => $featureFlags,
            'dashboardCharts' => $dashboardCharts,
            'storeCurrencyCode' => $storeCurrencyCode,
        ]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable, 3: CarbonImmutable, 4: int}
     */
    private function resolveRangeWindow(): array
    {
        $days = (int) $this->rangeDays;
        if (! in_array($days, [1, 7, 30], true)) {
            $days = 7;
        }

        $end = CarbonImmutable::now()->endOfDay();
        $start = CarbonImmutable::now()->startOfDay()->subDays($days - 1);
        $previousEnd = $start->subSecond();
        $previousStart = $start->subDays($days);

        return [$start, $end, $previousStart, $previousEnd, $days];
    }

    private function ordersInRange(CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return Order::query()->where(function (Builder $query) use ($start, $end): void {
            $query->whereBetween('placed_at', [$start, $end])
                ->orWhere(function (Builder $fallback) use ($start, $end): void {
                    $fallback->whereNull('placed_at')
                        ->whereBetween('created_at', [$start, $end]);
                });
        });
    }

    /**
     * @return array{current: float, previous: float, delta: float, direction: string, percent: float|null}
     */
    private function formatDelta(float|int $current, float|int $previous): array
    {
        $current = (float) $current;
        $previous = (float) $previous;
        $delta = $current - $previous;
        $direction = $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat');

        $percent = null;
        if (abs($previous) > 0.00001) {
            $percent = ($delta / abs($previous)) * 100;
        }

        return [
            'current' => $current,
            'previous' => $previous,
            'delta' => $delta,
            'direction' => $direction,
            'percent' => $percent,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildTrendRows(int $days): Collection
    {
        $end = CarbonImmutable::now()->endOfDay();
        $start = CarbonImmutable::now()->startOfDay()->subDays($days - 1);

        $map = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->addDays($i)->toDateString();
            $map[$date] = [
                'date' => $date,
                'orders' => 0,
                'revenue' => 0.0,
                'bar_width' => 0,
            ];
        }

        $rows = $this->ordersInRange($start, $end)
            ->get(['placed_at', 'created_at', 'grand_total']);

        foreach ($rows as $order) {
            $bucket = ($order->placed_at ?: $order->created_at)?->toDateString();
            if (! $bucket || ! array_key_exists($bucket, $map)) {
                continue;
            }

            $map[$bucket]['orders']++;
            $map[$bucket]['revenue'] = (float) $map[$bucket]['revenue'] + (float) $order->grand_total;
        }

        $maxRevenue = collect($map)->max('revenue');
        $maxRevenue = $maxRevenue > 0 ? (float) $maxRevenue : 1.0;

        foreach ($map as $key => $row) {
            $map[$key]['bar_width'] = (int) round(((float) $row['revenue'] / $maxRevenue) * 100);
        }

        return collect(array_values($map));
    }
}
