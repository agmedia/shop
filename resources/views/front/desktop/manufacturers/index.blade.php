@extends('front.desktop.layouts.store')

@section('title', 'Manufacturers')

@section('content')
    <section class="mb-8">
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">Manufacturers</h1>
        <p class="mt-2 text-slate-600">Explore brands and filter products by manufacturer pages.</p>
    </section>

    @if ($manufacturers->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">No active manufacturers available.</div>
    @else
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($manufacturers as $manufacturer)
                @php
                    $translation = $manufacturer->translations->firstWhere('locale', $locale)
                        ?? $manufacturer->translations->firstWhere('locale', $fallbackLocale);
                @endphp

                <a href="{{ route('manufacturers.show', ['slug' => $translation?->slug ?? $manufacturer->id]) }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <h2 class="text-lg font-semibold text-slate-900">{{ $translation?->name ?? $manufacturer->code }}</h2>
                    <p class="mt-2 line-clamp-3 text-sm text-slate-600">{{ $translation?->description ?: 'Manufacturer detail and assigned catalog products.' }}</p>
                    <p class="mt-4 text-sm font-semibold text-blue-700">{{ $manufacturer->products_count }} products</p>
                </a>
            @endforeach
        </div>
    @endif
@endsection
