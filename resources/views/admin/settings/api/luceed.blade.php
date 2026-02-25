<x-admin-layout :title="__('Settings / API / Luceed')">
    <div class="space-y-6">
        @include('admin.settings.api.partials.nav')
        <livewire:admin.settings.api.luceed-manager />
        <livewire:admin.settings.api.luceed-sync-manager />
    </div>
</x-admin-layout>
