@extends('front.desktop.layouts.store')

@section('title', __('ui.checkout.corvus.redirect_page_title'))

@section('content')
    <section class="border border-slate-200 bg-white p-8">
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">{{ __('ui.checkout.corvus.redirect_title') }}</h1>
        <p class="mt-2 text-sm text-slate-600">{{ __('ui.checkout.corvus.redirect_subtitle') }}</p>

        <form id="corvus-redirect-form" method="POST" action="{{ $formData['action_url'] }}" class="mt-6">
            @foreach (($formData['payload'] ?? []) as $field => $value)
                <input type="hidden" name="{{ $field }}" value="{{ (string) $value }}">
            @endforeach

            <button type="submit" class="border border-slate-900 bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                {{ __('ui.checkout.corvus.redirect_button') }}
            </button>
        </form>
    </section>

    @push('scripts')
        <script>
            window.setTimeout(function () {
                var form = document.getElementById('corvus-redirect-form');
                if (form) {
                    form.submit();
                }
            }, 120);
        </script>
    @endpush
@endsection
