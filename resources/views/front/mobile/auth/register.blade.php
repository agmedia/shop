@extends('front.mobile.layouts.store')

@section('title', __('ui.auth.register.page_title'))
@section('header_title', __('ui.auth.register.heading'))
@section('page_title', __('ui.auth.register.heading'))

@section('content')
    <div class="card card-style rounded-0">
        <div class="content">
            <h3 class="mb-1">{{ __('ui.auth.register.form_title') }}</h3>
            <p class="opacity-60 mb-3">{{ __('ui.auth.register.subheading') }}</p>

            <form method="POST" action="{{ route('front.auth.register.store') }}" novalidate>
                @csrf
                <input type="hidden" name="intended" value="{{ old('intended', (string) request('intended', route('account.dashboard'))) }}">

                <div class="mb-3">
                    <label class="mb-1 d-block font-600 font-12">{{ __('ui.auth.fields.first_name') }}</label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" class="form-control rounded-0" required>
                    @error('first_name')
                        <p class="mb-0 mt-1 font-600 font-12" style="color:#e11d48;">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="mb-1 d-block font-600 font-12">{{ __('ui.auth.fields.last_name') }}</label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" class="form-control rounded-0" required>
                    @error('last_name')
                        <p class="mb-0 mt-1 font-600 font-12" style="color:#e11d48;">{{ $message }}</p>
                    @enderror
                </div>

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

                <div class="mb-3">
                    <label class="mb-1 d-block font-600 font-12">{{ __('ui.auth.fields.password_confirmation') }}</label>
                    <input type="password" name="password_confirmation" class="form-control rounded-0" required>
                </div>

                <button type="submit" class="btn btn-full rounded-0 font-600 bg-highlight">{{ __('ui.auth.register.submit') }}</button>
            </form>
        </div>
    </div>

    <div class="card card-style rounded-0">
        <div class="content">
            <p class="font-600 font-12 mb-1 opacity-60">{{ __('ui.auth.register.have_account_eyebrow') }}</p>
            <h4 class="mb-2">{{ __('ui.auth.register.have_account_title') }}</h4>
            <p class="mb-3">{{ __('ui.auth.register.have_account_text') }}</p>
            <a href="{{ route('front.auth.login', ['intended' => (string) request('intended', route('account.dashboard'))]) }}" class="btn btn-full btn-border border-dark color-dark rounded-0 font-600">{{ __('ui.auth.register.go_to_login') }}</a>
        </div>
    </div>
@endsection
