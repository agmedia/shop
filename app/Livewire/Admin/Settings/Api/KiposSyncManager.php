<?php

namespace App\Livewire\Admin\Settings\Api;

use App\Jobs\RunKiposSyncActionJob;
use App\Models\Integrations\KiposSyncRun;
use App\Services\Integrations\Kipos\KiposSyncService;
use Livewire\Component;
use Livewire\WithPagination;
use Silber\Bouncer\BouncerFacade as Bouncer;

class KiposSyncManager extends Component
{
    use WithPagination;

    private const RUNS_PAGE_NAME = 'kiposSyncRunsPage';

    private const IMAGE_BATCH_SIZE = 10;

    public string $tab = 'actions';

    public string $importProductCodes = '';

    /**
     * @var array<string, mixed>
     */
    public array $syncForm = [
        'kipos_sync_default_locale' => 'hr',
        'kipos_sync_import_category_id' => null,
        'kipos_sync_size_option_id' => null,
        'kipos_sync_price_field' => 'CIJENA_MPC',
        'kipos_sync_action_price_field' => 'AKCIJSKA_CIJENA',
        'kipos_sync_stock_warehouse_ids' => '200',
        'kipos_sync_quantity_overrides' => '',
        'kipos_sync_color_overrides' => '{"W7042":"tamno-plava"}',
        'kipos_order_prefix' => 'KHR',
        'kipos_order_valuta' => '978',
        'kipos_order_customer_cms_id' => '1',
        'kipos_order_shipping_item_code' => '',
        'kipos_order_payment_fee_item_code' => '',
        'kipos_order_private_at_company_id' => 2,
        'kipos_order_private_de_company_id' => 3,
        'kipos_order_status_endpoint' => 'narudzba/statusi',
        'kipos_order_status_lookback_days' => 30,
        'kipos_order_status_codes' => '',
        'kipos_order_status_map' => '',
    ];

    public string $runningActionKey = '';

    public ?int $lastRunId = null;

    public function mount(KiposSyncService $syncService): void
    {
        $this->authorizeAccess();

        $this->syncForm = array_merge($this->syncForm, $syncService->syncSettings());

        $tab = (string) request()->query('tab', 'actions');
        if (in_array($tab, ['actions', 'settings', 'history', 'help'], true)) {
            $this->tab = $tab;
        }
    }

    public function setTab(string $tab): void
    {
        $this->authorizeAccess();

        if (! in_array($tab, ['actions', 'settings', 'history', 'help'], true)) {
            return;
        }

        $this->tab = $tab;
    }

    public function saveSyncSettings(KiposSyncService $syncService): void
    {
        $this->authorizeAccess();

        $validated = $this->validate($this->rules());
        $payload = $this->normalizeSyncPayload($validated['syncForm']);

        $syncService->saveSyncSettings($payload);
        $this->syncForm = array_merge($this->syncForm, $payload);

        $this->dispatch('notify', type: 'success', message: __('Kipos sync settings saved.'));
    }

    public function runAction(string $actionKey, KiposSyncService $syncService): void
    {
        $this->authorizeAccess();

        if ($actionKey === 'import_products') {
            $this->runProductImport($syncService);

            return;
        }

        $this->runningActionKey = $actionKey;
        $queuedRun = null;

        try {
            if ($this->runsInBrowserBatches($actionKey)) {
                @set_time_limit(0);

                $activeRun = $syncService->activeRun($actionKey);
                if ($activeRun && $activeRun->status === 'started' && ! (bool) data_get($activeRun->stats, 'browser_batch')) {
                    $this->lastRunId = $activeRun->id;
                    $this->dispatch('notify', type: 'info', message: __('This Kipos sync action is already running. Watch the run log below.'));

                    return;
                }

                $run = $syncService->startImageBatchRun($actionKey, auth()->id(), self::IMAGE_BATCH_SIZE, $activeRun);
                $run = $syncService->processImageBatchRun($run, self::IMAGE_BATCH_SIZE);
                $this->lastRunId = $run->id;

                if ($run->status === 'success') {
                    $this->dispatch('notify', type: 'success', message: __('Kipos image sync finished. Review the exact stats below.'));
                } else {
                    $this->dispatch('notify', type: 'info', message: __('Kipos image sync started. Progress updates below while this admin page stays open.'));
                }

                return;
            }

            if ($this->runsImmediately($actionKey)) {
                @set_time_limit(0);

                $activeRun = $syncService->activeRun($actionKey);
                if ($activeRun) {
                    $this->lastRunId = $activeRun->id;

                    if ($activeRun->status === 'queued') {
                        $run = $syncService->executeQueuedRun($activeRun);
                        $this->lastRunId = $run->id;
                        $this->dispatch('notify', type: 'success', message: __('Kipos sync action finished. Review the exact stats below.'));

                        return;
                    }

                    $this->dispatch('notify', type: 'info', message: __('This Kipos sync action is already queued or running. Watch the run log below.'));

                    return;
                }

                $run = $syncService->run($actionKey, auth()->id());
                $this->lastRunId = $run->id;
                $this->dispatch('notify', type: 'success', message: __('Kipos sync action finished. Review the exact stats below.'));

                return;
            }

            $queuedRun = $syncService->queue($actionKey, auth()->id());
            $this->lastRunId = $queuedRun->id;

            if (! $queuedRun->wasRecentlyCreated) {
                $this->dispatch('notify', type: 'info', message: __('This Kipos sync action is already queued or running. Watch the run log below.'));

                return;
            }

            RunKiposSyncActionJob::dispatch($queuedRun->id);
            $this->dispatch('notify', type: 'info', message: __('Kipos sync action queued. Background worker will process it and the run log will refresh here automatically. If the status stays `QUEUED`, the `kipos` queue worker is not active.'));
        } catch (\Throwable $exception) {
            if ($queuedRun instanceof KiposSyncRun && $queuedRun->status === 'queued') {
                $queuedRun->fill([
                    'status' => 'failed',
                    'summary' => 'Queue dispatch failed.',
                    'error_message' => $exception->getMessage(),
                    'finished_at' => now(),
                ])->save();
            }

            $this->dispatch('notify', type: 'error', message: __('Kipos sync action failed: :error', ['error' => $exception->getMessage()]));
        } finally {
            $this->runningActionKey = '';
            $this->resetPage(pageName: self::RUNS_PAGE_NAME);
        }
    }

    public function processActiveBrowserBatch(KiposSyncService $syncService): void
    {
        $this->authorizeAccess();

        $run = KiposSyncRun::query()
            ->where('action_key', 'update_images')
            ->where('status', 'started')
            ->latest('id')
            ->first();

        if (! $run || ! (bool) data_get($run->stats, 'browser_batch')) {
            return;
        }

        try {
            @set_time_limit(0);

            $run = $syncService->processImageBatchRun($run, self::IMAGE_BATCH_SIZE);
            $this->lastRunId = $run->id;

            if ($run->status === 'success') {
                $this->dispatch('notify', type: 'success', message: __('Kipos image sync finished. Review the exact stats below.'));
            }
        } catch (\Throwable $exception) {
            $this->lastRunId = $run->id;
            $this->dispatch('notify', type: 'error', message: __('Kipos sync action failed: :error', ['error' => $exception->getMessage()]));
        }
    }

    public function cancelRun(int $runId): void
    {
        $this->authorizeAccess();

        $run = KiposSyncRun::query()
            ->whereKey($runId)
            ->whereIn('status', ['queued', 'started'])
            ->first();

        if (! $run) {
            $this->dispatch('notify', type: 'info', message: __('This Kipos sync run is no longer active.'));

            return;
        }

        $run->fill([
            'status' => 'failed',
            'summary' => 'Execution stopped from admin.',
            'error_message' => 'This Kipos sync run was manually stopped from the admin screen.',
            'finished_at' => now(),
        ])->save();

        $this->lastRunId = $run->id;
        $this->resetPage(pageName: self::RUNS_PAGE_NAME);
        $this->dispatch('notify', type: 'success', message: __('Kipos sync run stopped.'));
    }

    private function runsInBrowserBatches(string $actionKey): bool
    {
        return in_array($actionKey, ['update_images'], true);
    }

    private function runsImmediately(string $actionKey): bool
    {
        return in_array($actionKey, ['update_prices', 'update_quantities', 'update_order_statuses'], true);
    }

    private function runProductImport(KiposSyncService $syncService): void
    {
        $this->resetErrorBag('importProductCodes');

        $codes = $this->normalizedImportProductCodes();
        if ($codes === []) {
            $this->addError('importProductCodes', __('Enter at least one Kipos product code.'));
            $this->dispatch('notify', type: 'error', message: __('Enter at least one Kipos product code before importing products.'));

            return;
        }

        $this->runningActionKey = 'import_products';

        try {
            @set_time_limit(0);

            $run = $syncService->runProductImport($codes, auth()->id());
            $this->lastRunId = $run->id;
            $this->importProductCodes = implode(', ', $codes);

            $this->dispatch('notify', type: 'success', message: __('Kipos product import finished. Review the exact stats below.'));
        } catch (\Throwable $exception) {
            $this->lastRunId = KiposSyncRun::query()
                ->where('action_key', 'import_products')
                ->latest('id')
                ->value('id');

            $this->dispatch('notify', type: 'error', message: __('Kipos sync action failed: :error', ['error' => $exception->getMessage()]));
        } finally {
            $this->runningActionKey = '';
            $this->resetPage(pageName: self::RUNS_PAGE_NAME);
        }
    }

    /**
     * @return array<int, string>
     */
    private function normalizedImportProductCodes(): array
    {
        $raw = trim($this->importProductCodes);
        if ($raw === '' || strlen($raw) > 1000) {
            return [];
        }

        return collect(preg_split('/[\s,;]+/', $raw) ?: [])
            ->map(fn ($code): string => strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function render(KiposSyncService $syncService)
    {
        $shouldPoll = $syncService->hasActiveRuns();
        $activeRuns = KiposSyncRun::query()
            ->with('initiator:id,name,email')
            ->whereIn('status', ['queued', 'started'])
            ->latest('id')
            ->get();
        $runs = KiposSyncRun::query()
            ->with('initiator:id,name,email')
            ->latest('id')
            ->paginate(12, ['*'], self::RUNS_PAGE_NAME);

        $lastRun = null;
        if ($this->lastRunId) {
            $lastRun = KiposSyncRun::query()->with('initiator:id,name,email')->find($this->lastRunId);
        }

        return view('livewire.admin.settings.api.kipos-sync-manager', [
            'actionGroups' => $syncService->actionGroups(),
            'endpointMap' => $syncService->endpointMap(),
            'activeRuns' => $activeRuns,
            'runs' => $runs,
            'lastRun' => $lastRun,
            'shouldPoll' => $shouldPoll,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'syncForm.kipos_sync_default_locale' => ['required', 'string', 'max:12'],
            'syncForm.kipos_sync_import_category_id' => ['nullable', 'integer', 'min:1'],
            'syncForm.kipos_sync_size_option_id' => ['nullable', 'integer', 'min:1'],
            'syncForm.kipos_sync_price_field' => ['required', 'string', 'max:60'],
            'syncForm.kipos_sync_action_price_field' => ['required', 'string', 'max:60'],
            'syncForm.kipos_sync_stock_warehouse_ids' => ['nullable', 'string', 'max:500'],
            'syncForm.kipos_sync_quantity_overrides' => ['nullable', 'string', 'max:6000'],
            'syncForm.kipos_sync_color_overrides' => ['nullable', 'string', 'max:6000'],
            'syncForm.kipos_order_prefix' => ['required', 'string', 'max:20'],
            'syncForm.kipos_order_valuta' => ['required', 'string', 'max:12'],
            'syncForm.kipos_order_customer_cms_id' => ['required', 'string', 'max:30'],
            'syncForm.kipos_order_shipping_item_code' => ['nullable', 'string', 'max:120'],
            'syncForm.kipos_order_payment_fee_item_code' => ['nullable', 'string', 'max:120'],
            'syncForm.kipos_order_private_at_company_id' => ['nullable', 'integer', 'min:1'],
            'syncForm.kipos_order_private_de_company_id' => ['nullable', 'integer', 'min:1'],
            'syncForm.kipos_order_status_endpoint' => ['required', 'string', 'max:255'],
            'syncForm.kipos_order_status_lookback_days' => ['required', 'integer', 'min:1', 'max:365'],
            'syncForm.kipos_order_status_codes' => ['nullable', 'string', 'max:500'],
            'syncForm.kipos_order_status_map' => ['nullable', 'string', 'max:6000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeSyncPayload(array $raw): array
    {
        return [
            'kipos_sync_default_locale' => strtolower(trim((string) ($raw['kipos_sync_default_locale'] ?? 'hr'))),
            'kipos_sync_import_category_id' => $this->nullablePositiveInt($raw['kipos_sync_import_category_id'] ?? null),
            'kipos_sync_size_option_id' => $this->nullablePositiveInt($raw['kipos_sync_size_option_id'] ?? null),
            'kipos_sync_price_field' => strtoupper(trim((string) ($raw['kipos_sync_price_field'] ?? 'CIJENA_MPC'))),
            'kipos_sync_action_price_field' => strtoupper(trim((string) ($raw['kipos_sync_action_price_field'] ?? 'AKCIJSKA_CIJENA'))),
            'kipos_sync_stock_warehouse_ids' => trim((string) ($raw['kipos_sync_stock_warehouse_ids'] ?? '')),
            'kipos_sync_quantity_overrides' => trim((string) ($raw['kipos_sync_quantity_overrides'] ?? '')),
            'kipos_sync_color_overrides' => trim((string) ($raw['kipos_sync_color_overrides'] ?? '')),
            'kipos_order_prefix' => strtoupper(trim((string) ($raw['kipos_order_prefix'] ?? 'KHR'))),
            'kipos_order_valuta' => trim((string) ($raw['kipos_order_valuta'] ?? '978')),
            'kipos_order_customer_cms_id' => trim((string) ($raw['kipos_order_customer_cms_id'] ?? '1')),
            'kipos_order_shipping_item_code' => strtoupper(trim((string) ($raw['kipos_order_shipping_item_code'] ?? ''))),
            'kipos_order_payment_fee_item_code' => strtoupper(trim((string) ($raw['kipos_order_payment_fee_item_code'] ?? ''))),
            'kipos_order_private_at_company_id' => $this->nullablePositiveInt($raw['kipos_order_private_at_company_id'] ?? null),
            'kipos_order_private_de_company_id' => $this->nullablePositiveInt($raw['kipos_order_private_de_company_id'] ?? null),
            'kipos_order_status_endpoint' => trim((string) ($raw['kipos_order_status_endpoint'] ?? 'narudzba/statusi')),
            'kipos_order_status_lookback_days' => max(1, min(365, (int) ($raw['kipos_order_status_lookback_days'] ?? 30))),
            'kipos_order_status_codes' => trim((string) ($raw['kipos_order_status_codes'] ?? '')),
            'kipos_order_status_map' => trim((string) ($raw['kipos_order_status_map'] ?? '')),
        ];
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();

        abort_unless(
            $user && (Bouncer::is($user)->an('superadmin') || $user->can('settings.api.manage')),
            403
        );
    }
}
