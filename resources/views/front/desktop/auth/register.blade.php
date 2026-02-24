@extends('front.desktop.layouts.store')

@section('title', __('ui.auth.register.page_title'))

@section('content')
    <section class="mb-8">
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">{{ __('ui.auth.register.heading') }}</h1>
        <p class="mt-2 text-slate-600">{{ __('ui.auth.register.subheading') }}</p>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <div class="border border-slate-200 bg-white p-6">
            <h2 class="text-xl font-bold text-slate-900">{{ __('ui.auth.register.form_title') }}</h2>

            <form method="POST" action="{{ route('front.auth.register.store') }}" class="mt-5 grid gap-4 md:grid-cols-2" novalidate>
                @csrf
                <input type="hidden" name="intended" value="{{ old('intended', (string) request('intended', route('account.dashboard'))) }}">

                <div>
                    <label for="auth-register-first-name" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.auth.fields.first_name') }}</label>
                    <input id="auth-register-first-name" type="text" name="first_name" value="{{ old('first_name') }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" autocomplete="given-name" required>
                    @error('first_name')
                        <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="auth-register-last-name" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.auth.fields.last_name') }}</label>
                    <input id="auth-register-last-name" type="text" name="last_name" value="{{ old('last_name') }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" autocomplete="family-name" required>
                    @error('last_name')
                        <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="auth-register-email" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.auth.fields.email') }}</label>
                    <input id="auth-register-email" type="email" name="email" value="{{ old('email') }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" autocomplete="email" required>
                    @error('email')
                        <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="auth-register-password" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.auth.fields.password') }}</label>
                    <input id="auth-register-password" type="password" name="password" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" autocomplete="new-password" required>
                    @error('password')
                        <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="auth-register-password-confirmation" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.auth.fields.password_confirmation') }}</label>
                    <input id="auth-register-password-confirmation" type="password" name="password_confirmation" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" autocomplete="new-password" required>
                </div>

                <div class="md:col-span-2">
                    <button type="submit" class="inline-flex h-11 w-full items-center justify-center border border-slate-900 bg-slate-900 px-6 text-sm font-semibold text-white hover:bg-slate-700">
                        {{ __('ui.auth.register.submit') }}
                    </button>
                </div>
            </form>
        </div>

        <aside class="border border-slate-200 bg-slate-50 p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.auth.register.have_account_eyebrow') }}</p>
            <h2 class="mt-2 text-2xl font-bold text-slate-900">{{ __('ui.auth.register.have_account_title') }}</h2>
            <p class="mt-3 text-sm text-slate-600">{{ __('ui.auth.register.have_account_text') }}</p>

            <a href="{{ route('front.auth.login', ['intended' => (string) request('intended', route('account.dashboard'))]) }}" class="mt-5 inline-flex h-11 items-center justify-center border border-slate-900 px-6 text-sm font-semibold text-slate-900 hover:bg-slate-100">
                {{ __('ui.auth.register.go_to_login') }}
            </a>
        </aside>
    </section>
@endsection
