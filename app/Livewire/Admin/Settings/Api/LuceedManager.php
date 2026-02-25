<?php

namespace App\Livewire\Admin\Settings\Api;

use App\Services\Integrations\Luceed\LuceedSdkService;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Silber\Bouncer\BouncerFacade as Bouncer;

class LuceedManager extends Component
{
    /**
     * @var array<string, mixed>
     */
    public array $form = [
        'luceed_api_enabled' => false,
        'luceed_api_base_uri' => '',
        'luceed_api_auth_type' => 'basic',
        'luceed_api_username' => '',
        'luceed_api_password' => '',
        'luceed_api_bearer_token' => '',
        'luceed_api_header_name' => 'X-Api-Key',
        'luceed_api_header_value' => '',
        'luceed_api_query_key' => 'api_key',
        'luceed_api_query_value' => '',
        'luceed_api_timeout_seconds' => 20,
        'luceed_api_verify_tls' => true,
        'luceed_api_probe' => LuceedSdkService::PROBE_SIFRARNICI,
    ];

    /**
     * @var array{probe:string,result_count:int,first_item:array<string,mixed>|null}|null
     */
    public ?array $lastProbeResult = null;

    public ?string $lastProbeError = null;

    public function mount(LuceedSdkService $luceed): void
    {
        $this->authorizeAccess();

        $this->form = array_merge($this->form, $luceed->getSettings());
    }

    public function toggleEnabled(): void
    {
        $this->authorizeAccess();

        $this->form['luceed_api_enabled'] = ! (bool) ($this->form['luceed_api_enabled'] ?? false);
    }

    public function toggleTls(): void
    {
        $this->authorizeAccess();

        $this->form['luceed_api_verify_tls'] = ! (bool) ($this->form['luceed_api_verify_tls'] ?? true);
    }

    public function save(LuceedSdkService $luceed): void
    {
        $this->authorizeAccess();

        $validated = $this->validate($this->rules());
        $payload = $this->normalizePayload($validated['form']);

        $luceed->saveSettings($payload);

        $this->dispatch('notify', type: 'success', message: __('Luceed API settings saved.'));
    }

    public function testConnection(LuceedSdkService $luceed): void
    {
        $this->authorizeAccess();

        $validated = $this->validate($this->rules());
        $payload = $this->normalizePayload($validated['form']);

        $this->lastProbeResult = null;
        $this->lastProbeError = null;

        try {
            $this->lastProbeResult = $luceed->testConnection((string) $payload['luceed_api_probe'], $payload);
            $this->dispatch('notify', type: 'success', message: __('Luceed connection test passed.'));
        } catch (\Throwable $exception) {
            $this->lastProbeError = $exception->getMessage();
            $this->dispatch('notify', type: 'error', message: __('Luceed connection test failed. Check endpoint/auth credentials.'));
        }
    }

    public function render()
    {
        return view('livewire.admin.settings.api.luceed-manager', [
            'probeOptions' => $this->probeOptions(),
            'authTypeOptions' => $this->authTypeOptions(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'form.luceed_api_enabled' => ['required', 'boolean'],
            'form.luceed_api_base_uri' => ['required', 'string', 'max:255', 'url:http,https'],
            'form.luceed_api_auth_type' => ['required', 'string', Rule::in(array_keys($this->authTypeOptions()))],
            'form.luceed_api_username' => ['nullable', 'string', 'max:190', 'required_if:form.luceed_api_auth_type,basic'],
            'form.luceed_api_password' => ['nullable', 'string', 'max:190', 'required_if:form.luceed_api_auth_type,basic'],
            'form.luceed_api_bearer_token' => ['nullable', 'string', 'max:1024', 'required_if:form.luceed_api_auth_type,bearer'],
            'form.luceed_api_header_name' => ['nullable', 'string', 'max:190', 'required_if:form.luceed_api_auth_type,header'],
            'form.luceed_api_header_value' => ['nullable', 'string', 'max:1024', 'required_if:form.luceed_api_auth_type,header'],
            'form.luceed_api_query_key' => ['nullable', 'string', 'max:190', 'required_if:form.luceed_api_auth_type,query'],
            'form.luceed_api_query_value' => ['nullable', 'string', 'max:1024', 'required_if:form.luceed_api_auth_type,query'],
            'form.luceed_api_timeout_seconds' => ['required', 'integer', 'min:2', 'max:120'],
            'form.luceed_api_verify_tls' => ['required', 'boolean'],
            'form.luceed_api_probe' => ['required', 'string', Rule::in(array_keys($this->probeOptions()))],
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private function normalizePayload(array $raw): array
    {
        return [
            'luceed_api_enabled' => (bool) ($raw['luceed_api_enabled'] ?? false),
            'luceed_api_base_uri' => trim((string) ($raw['luceed_api_base_uri'] ?? '')),
            'luceed_api_auth_type' => (string) ($raw['luceed_api_auth_type'] ?? 'basic'),
            'luceed_api_username' => trim((string) ($raw['luceed_api_username'] ?? '')),
            'luceed_api_password' => trim((string) ($raw['luceed_api_password'] ?? '')),
            'luceed_api_bearer_token' => trim((string) ($raw['luceed_api_bearer_token'] ?? '')),
            'luceed_api_header_name' => trim((string) ($raw['luceed_api_header_name'] ?? '')),
            'luceed_api_header_value' => trim((string) ($raw['luceed_api_header_value'] ?? '')),
            'luceed_api_query_key' => trim((string) ($raw['luceed_api_query_key'] ?? '')),
            'luceed_api_query_value' => trim((string) ($raw['luceed_api_query_value'] ?? '')),
            'luceed_api_timeout_seconds' => (int) ($raw['luceed_api_timeout_seconds'] ?? 20),
            'luceed_api_verify_tls' => (bool) ($raw['luceed_api_verify_tls'] ?? true),
            'luceed_api_probe' => (string) ($raw['luceed_api_probe'] ?? LuceedSdkService::PROBE_SIFRARNICI),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function authTypeOptions(): array
    {
        return [
            'basic' => __('Basic auth (username/password)'),
            'bearer' => __('Bearer token'),
            'header' => __('Custom header key/value'),
            'query' => __('Query key/value'),
            'none' => __('No authentication'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function probeOptions(): array
    {
        return [
            LuceedSdkService::PROBE_SIFRARNICI => __('Sifrarnici (codebooks)'),
            LuceedSdkService::PROBE_SKLADISTA => __('Skladista (warehouses)'),
            LuceedSdkService::PROBE_VRSTE_PLACANJA => __('Vrste placanja (payment types)'),
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
