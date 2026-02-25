<?php

namespace App\Livewire\Admin\Settings\Api;

use App\Models\Integrations\LuceedSyncRun;
use App\Services\Integrations\Luceed\LuceedSyncService;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Silber\Bouncer\BouncerFacade as Bouncer;

class LuceedSyncManager extends Component
{
    use WithPagination;

    private const RUNS_PAGE_NAME = 'luceedSyncRunsPage';

    public string $tab = 'actions';

    /**
     * @var array<string, mixed>
     */
    public array $syncForm = [
        'luceed_sync_default_locale' => 'en',
        'luceed_sync_article_limit' => 0,
        'luceed_sync_stock_warehouses' => '',
        'luceed_sync_partner_type' => 'sifra',
        'luceed_sync_partner_value' => '',
        'luceed_sync_partner_currency' => 'EUR',
        'luceed_sync_orders_lookback_days' => 30,
        'luceed_sync_status_codes' => '',
        'luceed_sync_status_duration_days' => 4,
        'luceed_sync_order_status_domain' => 'NaloziProdaje',
    ];

    public string $runningActionKey = '';

    public ?int $lastRunId = null;

    public function mount(LuceedSyncService $syncService): void
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

    public function saveSyncSettings(LuceedSyncService $syncService): void
    {
        $this->authorizeAccess();

        $validated = $this->validate($this->rules());
        $payload = $this->normalizeSyncPayload($validated['syncForm']);

        $syncService->saveSyncSettings($payload);
        $this->syncForm = array_merge($this->syncForm, $payload);

        $this->dispatch('notify', type: 'success', message: __('Luceed sync settings saved.'));
    }

    public function runAction(string $actionKey, LuceedSyncService $syncService): void
    {
        $this->authorizeAccess();

        $this->runningActionKey = $actionKey;

        try {
            $run = $syncService->run($actionKey, auth()->id());
            $this->lastRunId = $run->id;
            $this->dispatch('notify', type: 'success', message: $run->summary ?: __('Luceed sync action completed.'));
        } catch (\Throwable $exception) {
            $this->dispatch('notify', type: 'error', message: __('Luceed sync action failed: :error', ['error' => $exception->getMessage()]));
        } finally {
            $this->runningActionKey = '';
            $this->resetPage(pageName: self::RUNS_PAGE_NAME);
        }
    }

    public function render(LuceedSyncService $syncService)
    {
        $runs = LuceedSyncRun::query()
            ->with('initiator:id,name,email')
            ->latest('id')
            ->paginate(12, ['*'], self::RUNS_PAGE_NAME);

        $lastRun = null;
        if ($this->lastRunId) {
            $lastRun = LuceedSyncRun::query()->with('initiator:id,name,email')->find($this->lastRunId);
        }

        return view('livewire.admin.settings.api.luceed-sync-manager', [
            'actionGroups' => $syncService->actionGroups(),
            'runs' => $runs,
            'lastRun' => $lastRun,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'syncForm.luceed_sync_default_locale' => ['required', 'string', 'max:12'],
            'syncForm.luceed_sync_article_limit' => ['required', 'integer', 'min:0', 'max:100000'],
            'syncForm.luceed_sync_stock_warehouses' => ['nullable', 'string', 'max:2000'],
            'syncForm.luceed_sync_partner_type' => ['required', 'string', 'max:32'],
            'syncForm.luceed_sync_partner_value' => ['nullable', 'string', 'max:128'],
            'syncForm.luceed_sync_partner_currency' => ['required', 'string', 'max:8'],
            'syncForm.luceed_sync_orders_lookback_days' => ['required', 'integer', 'min:1', 'max:365'],
            'syncForm.luceed_sync_status_codes' => ['nullable', 'string', 'max:500'],
            'syncForm.luceed_sync_status_duration_days' => ['required', 'integer', 'min:1', 'max:60'],
            'syncForm.luceed_sync_order_status_domain' => ['required', 'string', 'max:64', Rule::in(['NaloziProdaje', 'Narudzbe'])],
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeSyncPayload(array $raw): array
    {
        return [
            'luceed_sync_default_locale' => strtolower(trim((string) ($raw['luceed_sync_default_locale'] ?? 'en'))),
            'luceed_sync_article_limit' => max(0, (int) ($raw['luceed_sync_article_limit'] ?? 0)),
            'luceed_sync_stock_warehouses' => trim((string) ($raw['luceed_sync_stock_warehouses'] ?? '')),
            'luceed_sync_partner_type' => trim((string) ($raw['luceed_sync_partner_type'] ?? 'sifra')),
            'luceed_sync_partner_value' => trim((string) ($raw['luceed_sync_partner_value'] ?? '')),
            'luceed_sync_partner_currency' => strtoupper(trim((string) ($raw['luceed_sync_partner_currency'] ?? 'EUR'))),
            'luceed_sync_orders_lookback_days' => max(1, (int) ($raw['luceed_sync_orders_lookback_days'] ?? 30)),
            'luceed_sync_status_codes' => trim((string) ($raw['luceed_sync_status_codes'] ?? '')),
            'luceed_sync_status_duration_days' => max(1, (int) ($raw['luceed_sync_status_duration_days'] ?? 4)),
            'luceed_sync_order_status_domain' => trim((string) ($raw['luceed_sync_order_status_domain'] ?? 'NaloziProdaje')),
        ];
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
