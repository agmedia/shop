@extends($layout)
@section('title', __('compliance.withdraw'))
@section('header_title', __('compliance.withdraw'))
@section('content')
<section class="withdrawal-panel" aria-labelledby="withdrawal-title">
    <h1 id="withdrawal-title">{{ __('compliance.withdraw') }}</h1>
    @if (isset($withdrawal))
        <p role="status">{{ __('compliance.sent') }}</p>
        <p>{{ __('compliance.received') }}: {{ $withdrawal->received_at->setTimezone('Europe/Berlin')->format('d.m.Y H:i:s T (P)') }}</p>
        <p>{{ __('compliance.reference') }}: {{ $withdrawal->id }}</p>
        <p>{{ $withdrawal->statement }}</p>
        <p>{{ $withdrawal->items ?: __('compliance.entire_order') }}</p>
    @elseif (isset($draft))
        <p>{{ __('compliance.review') }}</p>
        <dl>
            <dt>{{ __('compliance.name') }}</dt><dd>{{ $draft['customer_name'] }}</dd>
            <dt>{{ __('return_request.form.email') }}</dt><dd>{{ $draft['email'] }}</dd>
            <dt>{{ __('return_request.form.order_number') }}</dt><dd>{{ $draft['order_number'] }}</dd>
            <dt>{{ __('compliance.items') }}</dt><dd style="white-space:pre-wrap">{{ ($draft['items'] ?? '') ?: __('compliance.entire_order') }}</dd>
        </dl>
        <p>{{ __('compliance.statement', ['order' => $draft['order_number']]) }}</p>
        <form method="POST" action="{{ route('withdrawal.store') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <button type="submit">{{ __('compliance.confirm') }}</button>
        </form>
        <a href="{{ route('returns.create', ['returnRequestSlug' => __('return_request.slug'), 'withdrawal' => 1, 'draft' => $token]) }}">{{ __('compliance.edit') }}</a>
    @else
        @php($draft = session('withdrawal.'.request('draft'), []))
        <p>{{ __('compliance.intro') }}</p>
        @if ($errors->any())
            <div role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form method="POST" action="{{ route('withdrawal.preview') }}">
            @csrf
            <label for="withdrawal-name">{{ __('compliance.name') }}</label>
            <input id="withdrawal-name" name="customer_name" autocomplete="name" maxlength="191" required value="{{ old('customer_name', $draft['customer_name'] ?? '') }}">
            <label for="withdrawal-email">{{ __('return_request.form.email') }}</label>
            <input id="withdrawal-email" name="email" type="email" autocomplete="email" maxlength="191" required value="{{ old('email', $draft['email'] ?? '') }}">
            <label for="withdrawal-order">{{ __('return_request.form.order_number') }}</label>
            <input id="withdrawal-order" name="order_number" maxlength="80" required value="{{ old('order_number', $draft['order_number'] ?? '') }}">
            <label for="withdrawal-items">{{ __('compliance.items') }}</label>
            <textarea id="withdrawal-items" name="items" rows="4" maxlength="5000">{{ old('items', $draft['items'] ?? '') }}</textarea>
            <button type="submit">{{ __('compliance.next') }}</button>
        </form>
    @endif
</section>
<style>
.withdrawal-panel { max-width: 48rem; margin: 1rem auto; padding: 1.5rem; color:#172033; background:#fff; }
.withdrawal-panel h1 { font-size:1.6rem; margin-bottom:1rem; }
.withdrawal-panel p { margin:1rem 0; }
.withdrawal-panel label, .withdrawal-panel input, .withdrawal-panel textarea { display:block; width:100%; margin:.5rem 0 1rem; }
.withdrawal-panel input, .withdrawal-panel textarea { border:1px solid #667085; padding:.65rem; }
.withdrawal-panel button { background:#172033; color:white; padding:.8rem 1.2rem; margin:1rem 0; }
.withdrawal-panel a { text-decoration:underline; }
.withdrawal-panel dt { font-weight:bold; margin-top:.8rem; }
.withdrawal-panel dd { overflow-wrap:anywhere; }
</style>
@endsection
