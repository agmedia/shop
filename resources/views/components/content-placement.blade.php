@foreach ($items as $item)
    @php
        $block = $item['block'];
        $translation = $item['translation'];
        $slot = $item['slot'];
        $locale = app()->getLocale();
        $fallbackLocale = config('app.locale');
        $translationPayload = is_array($translation?->payload ?? null) ? $translation->payload : [];
        $wrapperClasses = trim((string) ($translationPayload['custom_classes'] ?? ''));
        $wrapperStyle = trim((string) ($translationPayload['bg_css'] ?? ''));

        $overridePrefix = (string) config('content_blocks.view_overrides.prefix', 'front.content-blocks.instances.');
        $codeOverride = $overridePrefix.$block->code;

        $overrideView = '';
        if (view()->exists($codeOverride)) {
            $overrideView = $codeOverride;
        }

        $partial = 'front.content-blocks.types.'.$block->type;

        $blockItems = $block->relationLoaded('items')
            ? $block->items
            : $block->items()->orderBy('sort_order')->orderBy('id')->get();

        $productIds = $blockItems->where('item_type', 'product')->pluck('item_id')->map(fn ($id) => (int) $id)->all();
        $categoryIds = $blockItems->where('item_type', 'category')->pluck('item_id')->map(fn ($id) => (int) $id)->all();
        $manufacturerIds = $blockItems->where('item_type', 'manufacturer')->pluck('item_id')->map(fn ($id) => (int) $id)->all();
        $blogIds = $blockItems->where('item_type', 'blog')->pluck('item_id')->map(fn ($id) => (int) $id)->all();

        $products = collect();
        if ($productIds !== []) {
            $products = \App\Models\Catalog\Product\Product::query()
                ->whereIn('id', $productIds)
                ->with(['translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale])])
                ->get()
                ->sortBy(fn ($row) => array_search((int) $row->id, $productIds, true))
                ->values();
        }

        $categories = collect();
        if ($categoryIds !== []) {
            $categories = \App\Models\Catalog\Category\Category::query()
                ->whereIn('id', $categoryIds)
                ->with(['translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale])])
                ->get()
                ->sortBy(fn ($row) => array_search((int) $row->id, $categoryIds, true))
                ->values();
        }

        $manufacturers = collect();
        if ($manufacturerIds !== []) {
            $manufacturers = \App\Models\Catalog\Manufacturer\Manufacturer::query()
                ->whereIn('id', $manufacturerIds)
                ->with(['translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale])])
                ->get()
                ->sortBy(fn ($row) => array_search((int) $row->id, $manufacturerIds, true))
                ->values();
        }

        $blogs = collect();
        if ($blogIds !== []) {
            $blogs = \App\Models\Content\Blog\BlogPost::query()
                ->whereIn('id', $blogIds)
                ->with(['translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale])])
                ->get()
                ->sortBy(fn ($row) => array_search((int) $row->id, $blogIds, true))
                ->values();
        }

        $comments = collect();
        if ((string) $block->type === 'five_star_reviews_carousel') {
            $translationPayload = is_array($translation?->payload ?? null) ? $translation->payload : [];
            $blockPayload = is_array($block->payload ?? null) ? $block->payload : [];
            $mergedPayload = array_merge($blockPayload, $translationPayload);
            $limit = max(1, (int) ($mergedPayload['items_limit'] ?? 6));
            $featuredOnly = (bool) ($mergedPayload['reviews_featured_only'] ?? false);

            $commentsQuery = \App\Models\Content\Support\Comment::query()
                ->where('commentable_type', \App\Models\Catalog\Product\Product::class)
                ->where('status', \App\Models\Content\Support\Comment::STATUS_APPROVED)
                ->where('rating', 5)
                ->whereNull('parent_id')
                ->whereIn('locale', [$locale, $fallbackLocale])
                ->orderByDesc('is_featured')
                ->orderByDesc('reviewed_at')
                ->orderByDesc('id');

            if ($featuredOnly) {
                $commentsQuery->where('is_featured', true);
            }

            $comments = $commentsQuery->limit($limit)->get();
        }
    @endphp

    @if ($wrapperClasses !== '' || $wrapperStyle !== '')
        <div @if($wrapperClasses !== '') class="{{ $wrapperClasses }}" @endif @if($wrapperStyle !== '') style="{{ $wrapperStyle }}" @endif>
    @endif
        @if ($overrideView !== '')
            @include($overrideView, [
                'block' => $block,
                'translation' => $translation,
                'slot' => $slot,
                'blockItems' => $blockItems,
                'products' => $products,
                'categories' => $categories,
                'manufacturers' => $manufacturers,
                'blogs' => $blogs,
                'comments' => $comments,
            ])
        @elseif (view()->exists($partial))
            @include($partial, [
                'block' => $block,
                'translation' => $translation,
                'slot' => $slot,
                'blockItems' => $blockItems,
                'products' => $products,
                'categories' => $categories,
                'manufacturers' => $manufacturers,
                'blogs' => $blogs,
                'comments' => $comments,
            ])
        @else
            @include('front.content-blocks.types.custom', [
                'block' => $block,
                'translation' => $translation,
                'slot' => $slot,
                'blockItems' => $blockItems,
                'products' => $products,
                'categories' => $categories,
                'manufacturers' => $manufacturers,
                'blogs' => $blogs,
                'comments' => $comments,
            ])
        @endif
    @if ($wrapperClasses !== '' || $wrapperStyle !== '')
        </div>
    @endif
@endforeach
