{{ __('compliance.receipt_subject', ['order' => $withdrawal->order_number]) }}

{{ __('compliance.received') }}: {{ $withdrawal->received_at->setTimezone('Europe/Berlin')->format('d.m.Y H:i:s T (P)') }}
{{ __('compliance.reference') }}: {!! $withdrawal->id !!}
{{ __('compliance.name') }}: {!! $withdrawal->customer_name !!}
{{ __('return_request.form.email') }}: {!! $withdrawal->email !!}
{{ __('return_request.form.order_number') }}: {!! $withdrawal->order_number !!}

{!! $withdrawal->statement !!}
{{ __('compliance.items') }}: {!! $withdrawal->items ?: __('compliance.entire_order') !!}

Bali d.o.o.
Hrupine 19, HR-40323 Prelog, Kroatien
webshop@balidoo.com
