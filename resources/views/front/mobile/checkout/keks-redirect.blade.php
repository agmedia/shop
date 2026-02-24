@extends('front.mobile.layouts.store')

@section('title', __('ui.checkout.keks.redirect_page_title'))
@section('header_title', __('ui.checkout.keks.redirect_page_title'))
@section('page_title', __('ui.checkout.keks.redirect_page_title'))

@section('content')
    <div class="card card-style rounded-0">
        <div class="content">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <h4 class="mb-0">{{ __('ui.checkout.keks.redirect_title') }}</h4>
                <img src="{{ asset('assets/payments/keks-logo.svg') }}" alt="KEKS Pay" style="height:28px; width:auto; max-width:140px;">
            </div>
            <p class="font-13 opacity-70 mb-3">{{ __('ui.checkout.keks.redirect_subtitle') }}</p>

            <div class="border rounded-sm p-3 mb-3" style="background:#f8fafc;">
                <p class="font-11 text-uppercase mb-2" style="letter-spacing:.08em;">{{ __('ui.checkout.keks.instructions_title') }}</p>
                <ol class="pl-3 mb-0" style="font-size:13px;">
                    <li>{{ __('ui.checkout.keks.steps.open_app') }}</li>
                    <li class="mt-1 d-flex align-items-center gap-1">{{ __('ui.checkout.keks.steps.tap_icon') }}
                        <img src="{{ asset('assets/payments/keks-scan-icon.svg') }}" alt="Scan" style="height:16px; width:16px;">
                    </li>
                    <li class="mt-1">{{ __('ui.checkout.keks.steps.tap_scan_qr') }}</li>
                    <li class="mt-1">{{ __('ui.checkout.keks.steps.scan_qr') }}</li>
                </ol>
            </div>

            <img src="{{ (string) ($sellData['qr_image_url'] ?? '') }}" alt="KEKS Pay QR" class="img-fluid border rounded-sm p-2 bg-white mb-3" style="width:100%; max-width:320px; margin:0 auto; display:block;">

            <a href="{{ (string) ($sellData['deeplink'] ?? '#') }}" class="btn btn-margins btn-full gradient-blue font-13 btn-l font-700 rounded-sm mb-2">
                {{ __('ui.checkout.keks.open_app_button') }}
            </a>
            <a href="{{ route('checkout.success', ['orderNumber' => $order->order_number]) }}" class="btn btn-margins btn-full border border-slate-400 font-13 btn-l font-600 rounded-sm text-slate-700">
                {{ __('ui.checkout.keks.continue_without_payment') }}
            </a>
        </div>
    </div>
@endsection
