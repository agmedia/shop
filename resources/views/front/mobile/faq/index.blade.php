@extends('front.mobile.layouts.store')

@section('title', __('ui.faq.page_title'))
@section('header_title', __('ui.faq.title'))
@section('page_title', __('ui.faq.title'))

@section('content')
    @if ($topBlocks->isNotEmpty())
        @include('components.content-placement', ['items' => $topBlocks])
    @endif

    <div class="card card-style rounded-0">
        <div class="content">
            <div class="bg-light border p-3 mb-3 text-center">
                <h2 class="mb-1">{{ __('ui.faq.title') }}</h2>
                <p class="mb-0">{{ __('ui.faq.subtitle') }}</p>
            </div>

            @forelse ($faqs as $faq)
                @php
                    $translation = $faq->translations->firstWhere('locale', $locale)
                        ?? $faq->translations->firstWhere('locale', $fallbackLocale)
                        ?? $faq->translations->first();
                @endphp
                @if ($translation)
                    <details class="group border-bottom" @if($loop->first) open @endif>
                        <summary class="d-flex justify-content-between align-items-start py-3" style="list-style:none;cursor:pointer;">
                            <span class="font-600 text-dark pe-3">{{ $translation->question }}</span>
                            <span class="text-secondary transition group-open:rotate-45 fs-3 lh-1">+</span>
                        </summary>
                        <div class="content-richtext font-13 pb-3">
                            {!! $translation->answer_html ?: '<p>—</p>' !!}
                        </div>
                    </details>
                @endif
            @empty
                <p class="text-secondary mb-0">{{ __('ui.faq.empty') }}</p>
            @endforelse
        </div>
    </div>

    @if ($bottomBlocks->isNotEmpty())
        @include('components.content-placement', ['items' => $bottomBlocks])
    @endif
@endsection
