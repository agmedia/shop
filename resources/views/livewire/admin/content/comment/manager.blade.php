<div class="space-y-6">
    <div class="admin-panel admin-search-panel p-6">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">{{ __('Comments Moderation') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ __('Review and moderate user comments across product, blog, page, and FAQ content.') }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ __('Items per page') }}: <span class="admin-chip">{{ $perPage }}</span></p>
            </div>

            <div class="flex w-[74rem] max-w-full items-end justify-end gap-3">
                <div class="grid w-full max-w-[66rem] items-end gap-3" style="grid-template-columns: minmax(20rem, 1.4fr) 9rem 10rem 8rem;">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Search') }}</label>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Body, author, email...') }}" class="admin-search-input w-full rounded-xl border px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Status') }}</label>
                        <select wire:model.live="status" data-tom-select data-tom-no-search="1" class="admin-search-input admin-select w-full rounded-xl border px-3 py-2 text-sm">
                            @foreach ($statusOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Target') }}</label>
                        <select wire:model.live="target" data-tom-select data-tom-no-search="1" class="admin-search-input admin-select w-full rounded-xl border px-3 py-2 text-sm">
                            @foreach ($targetOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Locale') }}</label>
                        <select wire:model.live="locale" data-tom-select data-tom-no-search="1" class="admin-search-input admin-select w-full rounded-xl border px-3 py-2 text-sm lowercase">
                            <option value="all">{{ __('all') }}</option>
                            @foreach ($adminLocaleOptions as $localeOption)
                                <option value="{{ $localeOption }}">{{ $localeOption }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-panel admin-panel-soft p-5">
        <h2 class="admin-section-title">{{ __('Items') }}</h2>

        <div class="mt-4 overflow-x-auto">
            <table class="admin-items-table min-w-full text-sm">
                <thead class="text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold">{{ __('Comment') }}</th>
                        <th class="px-3 py-2 text-left font-semibold">{{ __('Target') }}</th>
                        <th class="px-3 py-2 text-center font-semibold">{{ __('Rating') }}</th>
                        <th class="px-3 py-2 text-center font-semibold">{{ __('Status') }}</th>
                        <th class="px-3 py-2 text-center font-semibold">{{ __('Created') }}</th>
                        <th class="px-3 py-2 text-right font-semibold">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        @php
                            $commentable = $row->commentable;
                            $targetLabel = '-';
                            if ($commentable instanceof \App\Models\Catalog\Product\Product) {
                                $targetLabel = __('Product: ').($commentable->translations->first()?->name ?? $commentable->code);
                            } elseif ($commentable instanceof \App\Models\Content\Blog\BlogPost) {
                                $targetLabel = __('Blog: ').($commentable->translations->first()?->title ?? $commentable->code);
                            } elseif ($commentable instanceof \App\Models\Content\Page\InfoPage) {
                                $targetLabel = __('Page: ').($commentable->translations->first()?->title ?? $commentable->code);
                            } elseif ($commentable instanceof \App\Models\Content\Support\Faq) {
                                $targetLabel = __('FAQ: ').($commentable->translations->first()?->question ?? $commentable->code);
                            }
                        @endphp
                        <tr class="{{ $row->trashed() ? 'bg-slate-50/70' : '' }}">
                            <td class="px-3 py-2 text-slate-800">
                                <div class="line-clamp-3">{{ $row->body }}</div>
                                <div class="mt-1 text-xs text-slate-500">
                                    {{ $row->author_name ?: ($row->user?->name ?? __('Anonymous')) }}
                                    @if ($row->author_email || $row->user?->email)
                                        ({{ $row->author_email ?: $row->user?->email }})
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-2 text-slate-700">{{ $targetLabel }}</td>
                            <td class="px-3 py-2 text-center text-slate-700">{{ $row->rating ?: '-' }}</td>
                            <td class="px-3 py-2 text-center">
                                @php
                                    $statusColor = match ($row->status) {
                                        'approved' => 'bg-emerald-100 text-emerald-800',
                                        'rejected' => 'bg-amber-100 text-amber-800',
                                        'spam' => 'bg-rose-100 text-rose-800',
                                        default => 'bg-slate-200 text-slate-700',
                                    };
                                @endphp
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusColor }}">
                                    {{ ucfirst($row->status) }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-center text-xs text-slate-600">
                                {{ $row->created_at?->format('Y-m-d H:i') }}
                                @if ($row->reviewed_at)
                                    <div class="text-[11px] text-slate-500">{{ __('Reviewed:') }} {{ $row->reviewed_at->format('Y-m-d H:i') }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right">
                                @if ($row->trashed())
                                    <button type="button" wire:click="restore({{ $row->id }})" class="rounded-lg border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                                        {{ __('Restore') }}
                                    </button>
                                @else
                                    <div class="inline-flex flex-wrap items-center justify-end gap-1">
                                        <button type="button" wire:click="approve({{ $row->id }})" class="rounded-lg border border-emerald-300 px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">
                                            {{ __('Approve') }}
                                        </button>
                                        <button type="button" wire:click="reject({{ $row->id }})" class="rounded-lg border border-amber-300 px-2 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-50">
                                            {{ __('Reject') }}
                                        </button>
                                        <button type="button" wire:click="spam({{ $row->id }})" class="rounded-lg border border-rose-300 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                                            {{ __('Spam') }}
                                        </button>
                                        <button type="button" wire:click="delete({{ $row->id }})" class="rounded-lg border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                                            {{ __('Trash') }}
                                        </button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-sm text-slate-500">{{ __('No comments for selected filter.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $rows->links() }}
        </div>
    </div>
</div>
