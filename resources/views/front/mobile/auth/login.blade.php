@extends('front.mobile.layouts.store')

@section('title', __('ui.auth.login.page_title'))
@section('header_title', __('ui.auth.login.heading'))
@section('page_title', __('ui.auth.login.heading'))

@section('content')
    <div class="card card-style rounded-0">
        <div class="content">
            <h3 class="mb-1">{{ __('ui.auth.login.form_title') }}</h3>
            <p class="opacity-60 mb-3">{{ __('ui.auth.login.subheading') }}</p>

            <form method="POST" action="{{ route('front.auth.login.store') }}" novalidate>
                @csrf
                <input type="hidden" name="intended" value="{{ old('intended', (string) request('intended', route('account.dashboard'))) }}">

                <div class="mb-3">
                    <label class="mb-1 d-block font-600 font-12">{{ __('ui.auth.fields.email') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control rounded-0" required>
                    @error('email')
                        <p class="mb-0 mt-1 font-600 font-12" style="color:#e11d48;">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="mb-1 d-block font-600 font-12">{{ __('ui.auth.fields.password') }}</label>
                    <input type="password" name="password" class="form-control rounded-0" required>
                    @error('password')
                        <p class="mb-0 mt-1 font-600 font-12" style="color:#e11d48;">{{ $message }}</p>
                    @enderror
                </div>

                <label class="d-flex align-items-center gap-2 mb-3">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    <span>{{ __('ui.auth.login.remember') }}</span>
                </label>

                <button type="submit" class="btn btn-full rounded-0 font-600 bg-highlight">{{ __('ui.auth.login.submit') }}</button>
            </form>
        </div>
    </div>

    <div class="card card-style rounded-0">
        <div class="content">
            <p class="font-600 font-12 mb-1 opacity-60">{{ __('ui.auth.login.new_customer_eyebrow') }}</p>
            <h4 class="mb-2">{{ __('ui.auth.login.new_customer_title') }}</h4>
            <p class="mb-3">{{ __('ui.auth.login.new_customer_text') }}</p>
            <a href="{{ route('front.auth.register', ['intended' => (string) request('intended', route('account.dashboard'))]) }}" class="btn btn-full btn-border border-dark color-dark rounded-0 font-600">{{ __('ui.auth.login.go_to_register') }}</a>
        </div>
    </div>
@endsection
