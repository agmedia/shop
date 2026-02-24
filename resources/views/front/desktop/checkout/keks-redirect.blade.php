@extends('front.desktop.layouts.store')

@section('title', __('ui.checkout.keks.redirect_page_title'))

@section('content')
    <section class="border border-slate-200 bg-white p-4 sm:p-6 lg:p-8">
        <div class="mx-auto max-w-4xl">
            <div class="flex flex-col items-start justify-between gap-4 border-b border-slate-200 pb-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">{{ __('ui.checkout.keks.redirect_title') }}</h1>
                    <p class="mt-2 text-sm text-slate-600">{{ __('ui.checkout.keks.redirect_subtitle') }}</p>
                </div>
                <img src="{{ asset('assets/payments/keks-logo.svg') }}" alt="KEKS Pay" class="h-10 w-auto max-w-[180px]">
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-700">{{ __('ui.checkout.keks.instructions_title') }}</h2>
                    <ol class="mt-3 space-y-2 text-sm text-slate-700">
                        <li>1. {{ __('ui.checkout.keks.steps.open_app') }}</li>
                        <li class="flex items-center gap-2">2. {{ __('ui.checkout.keks.steps.tap_icon') }}
                            <img src="{{ asset('assets/payments/keks-scan-icon.svg') }}" alt="Scan" class="h-5 w-5 shrink-0">
                        </li>
                        <li>3. {{ __('ui.checkout.keks.steps.tap_scan_qr') }}</li>
                        <li>4. {{ __('ui.checkout.keks.steps.scan_qr') }}</li>
                    </ol>

                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <a href="{{ (string) ($sellData['deeplink'] ?? '#') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-900 bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                            {{ __('ui.checkout.keks.open_app_button') }}
                        </a>
                        <a href="{{ route('checkout.success', ['orderNumber' => $order->order_number]) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                            {{ __('ui.checkout.keks.continue_without_payment') }}
                        </a>
                    </div>
                </div>

                <div class="mx-auto w-full max-w-[360px] rounded-xl border border-slate-200 bg-white p-4 text-center shadow-sm">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.checkout.keks.qr_label') }}</p>
                    <img
                        src="{{ (string) ($sellData['qr_image_url'] ?? '') }}"
                        alt="KEKS Pay QR"
                        class="mx-auto h-auto w-full max-w-[320px] rounded-lg border border-slate-200 bg-white p-2"
                    >
                </div>
            </div>
        </div>
    </section>
@endsection
