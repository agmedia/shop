<li>
    <a href="{{ route('returns.create', ['returnRequestSlug' => __('return_request.slug'), 'withdrawal' => 1]) }}" class="{{ $withdrawalLinkClass ?? 'transition hover:text-slate-900' }}">{{ __('compliance.withdraw') }}</a>
</li>
