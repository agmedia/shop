<x-admin-layout :title="__('Settings / API')">
    <div class="space-y-6">
        @include('admin.settings.api.partials.nav')
        @php
            $features = app(\App\Services\Catalog\CatalogFeatureService::class);
        @endphp
        @if ($features->useApi())
            <livewire:admin.settings.api.manager />
        @elseif ($features->useLuceedApi())
            <livewire:admin.settings.api.luceed-manager />
        @else
            <div class="admin-panel border border-amber-300 bg-amber-50 p-6 text-sm text-amber-900">
                {{ __('No API modules are enabled. Enable Wholesale API or Luceed API in Catalog Features.') }}
            </div>
        @endif
    </div>
</x-admin-layout>
