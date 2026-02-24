@extends('front.desktop.layouts.store')

@php
    $categoryTranslation = $category->translations->firstWhere('locale', $locale)
        ?? $category->translations->firstWhere('locale', $fallbackLocale);
@endphp

@section('title', $categoryTranslation?->name ?? 'Pages')

@section('content')
    @if ($topBlocks->isNotEmpty())
        <section class="mb-8">@include('components.content-placement', ['items' => $topBlocks])</section>
    @endif

    <section class="mb-8 px-1">
        <nav aria-label="Breadcrumb" class="mb-3 text-center">
            <ol class="inline-flex items-center justify-center gap-2 text-xs font-medium uppercase tracking-wide text-slate-500">
                <li><a href="{{ route('home') }}" class="hover:text-slate-700">{{ __('ui.front.desktop.footer.home') }}</a></li>
                <li class="text-slate-400">/</li>
                <li class="text-slate-700">{{ $categoryTranslation?->name ?? __('ui.front.desktop.footer.info') }}</li>
            </ol>
        </nav>

        <div class="bg-slate-100 px-8 py-8 text-center">
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">{{ $categoryTranslation?->name ?? __('ui.front.desktop.footer.info') }}</h1>
            @if (!empty($categoryTranslation?->clean_description))
                <p class="mt-2 text-slate-600">{{ $categoryTranslation->clean_description }}</p>
            @endif
        </div>
    </section>

    @if ($pages->isEmpty())
        <div class="rounded-none border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
            No pages in this category.
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($pages as $page)
                @php
                    $translation = $page->translations->firstWhere('locale', $locale)
                        ?? $page->translations->firstWhere('locale', $fallbackLocale);
                @endphp
                <article class="border border-slate-200 bg-white p-5">
                    <h2 class="text-lg font-bold text-slate-900">{{ $translation?->title ?? $page->code }}</h2>
                    @if (!empty($translation?->excerpt))
                        <p class="mt-2 text-sm text-slate-600">{{ $translation->excerpt }}</p>
                    @endif
                    <a href="{{ route('pages.show', ['slug' => $translation?->slug ?? $page->id]) }}" class="mt-4 inline-flex text-sm font-semibold text-slate-900 underline underline-offset-2 hover:text-slate-700">
                        Open page
                    </a>
                </article>
            @endforeach
        </div>

        <div class="mt-10">
            {{ $pages->links() }}
        </div>
    @endif

    @if ($bottomBlocks->isNotEmpty())
        <section class="mt-10">@include('components.content-placement', ['items' => $bottomBlocks])</section>
    @endif
@endsection
