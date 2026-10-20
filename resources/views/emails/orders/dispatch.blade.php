<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><title>{{ __('compliance.dispatch_subject', ['order' => $order->order_number]) }}</title></head>
<body style="font-family:Arial,sans-serif;line-height:1.6;color:#172033">
<h1>{{ __('compliance.dispatch_subject', ['order' => $order->order_number]) }}</h1>
<p>{{ __('compliance.dispatch_body') }}</p>
<ul>@foreach ($order->items as $item)<li>{{ $item->name }} — {{ $item->sku }} × {{ $item->quantity }}</li>@endforeach</ul>
<p>{{ __('mail.orders.total') }}: {{ \App\Support\Currency::format((float) $order->grand_total) }}</p>
<p>Bali d.o.o.<br>Hrupine 19, HR-40323 Prelog, Kroatien<br><a href="mailto:webshop@balidoo.com">webshop@balidoo.com</a></p>
</body>
</html>
