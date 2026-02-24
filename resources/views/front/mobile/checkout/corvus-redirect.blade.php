@extends('front.mobile.layouts.store')

@section('title', __('ui.checkout.corvus.redirect_page_title'))
@section('header_title', __('ui.checkout.corvus.redirect_page_title'))
@section('page_title', __('ui.checkout.corvus.redirect_page_title'))

@section('content')
    <div class="card card-style rounded-0">
        <div class="content">
            <h4 class="mb-2">{{ __('ui.checkout.corvus.redirect_title') }}</h4>
            <p class="font-13 opacity-70 mb-3">{{ __('ui.checkout.corvus.redirect_subtitle') }}</p>

            <form id="corvus-redirect-form-mobile" method="POST" action="{{ $formData['action_url'] }}">
                @foreach (($formData['payload'] ?? []) as $field => $value)
                    <input type="hidden" name="{{ $field }}" value="{{ (string) $value }}">
                @endforeach

                <button type="submit" class="btn btn-margins btn-full gradient-blue font-13 btn-l font-600 rounded-sm">
                    {{ __('ui.checkout.corvus.redirect_button') }}
                </button>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            window.setTimeout(function () {
                var form = document.getElementById('corvus-redirect-form-mobile');
                if (form) {
                    form.submit();
                }
            }, 120);
        </script>
    @endpush
@endsection
