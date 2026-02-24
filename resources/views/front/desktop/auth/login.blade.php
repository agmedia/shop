@extends('front.desktop.layouts.store')

@section('title', __('ui.auth.login.page_title'))

@section('content')
    <section class="mb-8">
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">{{ __('ui.auth.login.heading') }}</h1>
        <p class="mt-2 text-slate-600">{{ __('ui.auth.login.subheading') }}</p>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <div class="border border-slate-200 bg-white p-6">
            <h2 class="text-xl font-bold text-slate-900">{{ __('ui.auth.login.form_title') }}</h2>

            <form method="POST" action="{{ route('front.auth.login.store') }}" class="mt-5 space-y-4" novalidate>
                @csrf
                <input type="hidden" name="intended" value="{{ old('intended', (string) request('intended', route('account.dashboard'))) }}">

                <div>
                    <label for="auth-login-email" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.auth.fields.email') }}</label>
                    <input id="auth-login-email" type="email" name="email" value="{{ old('email') }}" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" autocomplete="email" required>
                    @error('email')
                        <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="auth-login-password" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.auth.fields.password') }}</label>
                    <input id="auth-login-password" type="password" name="password" class="h-11 w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-0" autocomplete="current-password" required>
                    @error('password')
                        <p class="mt-2 text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 border-slate-300 text-slate-900 focus:ring-0" @checked(old('remember'))>
                    {{ __('ui.auth.login.remember') }}
                </label>

                <button type="submit" class="inline-flex h-11 w-full items-center justify-center border border-slate-900 bg-slate-900 px-6 text-sm font-semibold text-white hover:bg-slate-700">
                    {{ __('ui.auth.login.submit') }}
                </button>
            </form>
        </div>

        <aside class="border border-slate-200 bg-slate-50 p-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('ui.auth.login.new_customer_eyebrow') }}</p>
            <h2 class="mt-2 text-2xl font-bold text-slate-900">{{ __('ui.auth.login.new_customer_title') }}</h2>
            <p class="mt-3 text-sm text-slate-600">{{ __('ui.auth.login.new_customer_text') }}</p>

            <a href="{{ route('front.auth.register', ['intended' => (string) request('intended', route('account.dashboard'))]) }}" class="mt-5 inline-flex h-11 items-center justify-center border border-slate-900 px-6 text-sm font-semibold text-slate-900 hover:bg-slate-100">
                {{ __('ui.auth.login.go_to_register') }}
            </a>
        </aside>
    </section>
@endsection
