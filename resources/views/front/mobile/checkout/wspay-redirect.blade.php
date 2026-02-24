@extends('front.mobile.layouts.store')

@section('title', __('ui.checkout.wspay.redirect_page_title'))
@section('header_title', __('ui.checkout.wspay.redirect_page_title'))
@section('page_title', __('ui.checkout.wspay.redirect_page_title'))

@section('content')
    <div class="card card-style rounded-0">
        <div class="content">
            <h4 class="mb-2">{{ __('ui.checkout.wspay.redirect_title') }}</h4>
            <p class="font-13 opacity-70 mb-3">{{ __('ui.checkout.wspay.redirect_subtitle') }}</p>

            <form id="wspay-redirect-form-mobile" method="POST" action="{{ $formData['action_url'] }}">
                <input type="hidden" name="ShopID" value="{{ $formData['shop_id'] }}">
                <input type="hidden" name="ShoppingCartID" value="{{ $formData['shopping_cart_id'] }}">
                <input type="hidden" name="Version" value="{{ $formData['version'] }}">
                <input type="hidden" name="TotalAmount" value="{{ $formData['total_amount'] }}">
                <input type="hidden" name="ReturnURL" value="{{ $formData['return_url'] }}">
                <input type="hidden" name="ReturnErrorURL" value="{{ $formData['return_error_url'] }}">
                <input type="hidden" name="CancelURL" value="{{ $formData['cancel_url'] }}">
                <input type="hidden" name="ReturnMethod" value="{{ $formData['return_method'] }}">
                <input type="hidden" name="Signature" value="{{ $formData['signature'] }}">
                <input type="hidden" name="CustomerFirstName" value="{{ (string) ($formData['customer']['first_name'] ?? '') }}">
                <input type="hidden" name="CustomerLastName" value="{{ (string) ($formData['customer']['last_name'] ?? '') }}">
                <input type="hidden" name="CustomerAddress" value="{{ (string) ($formData['customer']['address'] ?? '') }}">
                <input type="hidden" name="CustomerCity" value="{{ (string) ($formData['customer']['city'] ?? '') }}">
                <input type="hidden" name="CustomerZIP" value="{{ (string) ($formData['customer']['zip'] ?? '') }}">
                <input type="hidden" name="CustomerCountry" value="{{ (string) ($formData['customer']['country'] ?? '') }}">
                <input type="hidden" name="CustomerPhone" value="{{ (string) ($formData['customer']['phone'] ?? '') }}">
                <input type="hidden" name="CustomerEmail" value="{{ (string) ($formData['customer']['email'] ?? '') }}">

                <button type="submit" class="btn btn-margins btn-full gradient-blue font-13 btn-l font-600 rounded-sm">
                    {{ __('ui.checkout.wspay.redirect_button') }}
                </button>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            window.setTimeout(function () {
                var form = document.getElementById('wspay-redirect-form-mobile');
                if (form) {
                    form.submit();
                }
            }, 120);
        </script>
    @endpush
@endsection
