@extends('front.desktop.layouts.store')

@php
    $translation = $selectedTranslation
        ?? $page->translations->firstWhere('locale', $locale)
        ?? $page->translations->firstWhere('locale', $fallbackLocale);
@endphp

@section('title', $translation?->title ?? 'Page')

@section('content')
    @if ($topBlocks->isNotEmpty())
        <section class="mb-8">@include('components.content-placement', ['items' => $topBlocks])</section>
    @endif

    <section class="mb-8 px-1">
        <nav aria-label="Breadcrumb" class="mb-3 text-center">
            <ol class="inline-flex items-center justify-center gap-2 text-xs font-medium uppercase tracking-wide text-slate-500">
                <li><a href="{{ route('home') }}" class="hover:text-slate-700">{{ __('ui.front.desktop.footer.home') }}</a></li>
                <li class="text-slate-400">/</li>
                <li class="text-slate-700">{{ $translation?->title ?? $page->code }}</li>
            </ol>
        </nav>
        <div class="bg-slate-100 px-8 py-8 text-center">
            <h1 class="text-4xl font-extrabold tracking-tight text-slate-900">{{ $translation?->title ?? $page->code }}</h1>
            @if (!empty($translation?->excerpt))
                <p class="mt-2 text-slate-600">{{ $translation->excerpt }}</p>
            @endif
        </div>
    </section>

    <article class="bg-white px-2 py-2">
        <div class="content-richtext">
            {!! $translation?->body_html ?: '<p>This page has no body content.</p>' !!}
        </div>
    </article>

    @if ($bottomBlocks->isNotEmpty())
        <section class="mt-10">@include('components.content-placement', ['items' => $bottomBlocks])</section>
    @endif
@endsection
