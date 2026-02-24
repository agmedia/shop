@extends('front.desktop.layouts.store')

@section('title', 'Categories')

@section('content')
    <section class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">Categories</h1>
            <p class="mt-2 text-slate-600">Browse catalog categories and jump into dedicated category pages.</p>
        </div>

        <form method="GET" action="{{ route('categories.index') }}" class="flex items-center gap-2">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search category" class="w-64 rounded-lg border-slate-300 text-sm">
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Search</button>
        </form>
    </section>

    @if ($categories->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">No categories found.</div>
    @else
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($categories as $category)
                @php
                    $translation = $category->translations->firstWhere('locale', $locale)
                        ?? $category->translations->firstWhere('locale', $fallbackLocale);
                @endphp

                <a href="{{ route('categories.show', ['slug' => $translation?->slug ?? $category->id]) }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <h2 class="text-lg font-semibold text-slate-900">{{ $translation?->name ?? $category->code }}</h2>
                    <p class="mt-2 line-clamp-3 text-sm text-slate-600">{{ $translation?->clean_description ?: 'Category products and merchandising blocks.' }}</p>
                    <p class="mt-4 text-sm font-semibold text-blue-700">{{ $category->products_count }} products</p>
                </a>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $categories->links() }}
        </div>
    @endif
@endsection
