@if ((bool) ($storeSettings['announcement']['enabled'] ?? true))
    @php
        $announcementText = (string) ($storeSettings['announcement']['text'] ?? __('ui.front.desktop.promo_bar'));
        $announcementUrl = trim((string) ($storeSettings['announcement']['url'] ?? ''));
        $announcementNewTab = (bool) ($storeSettings['announcement']['new_tab'] ?? false);
        $announcementScrollEnabled = (bool) ($storeSettings['announcement']['scroll_enabled'] ?? false);
        $announcementScrollDuration = (int) ($storeSettings['announcement']['scroll_duration_seconds'] ?? 18);
        $announcementBackgroundColor = (string) ($storeSettings['announcement']['background_color'] ?? '#000000');
        $announcementTextColor = (string) ($storeSettings['announcement']['text_color'] ?? '#ffffff');
    @endphp
    <div class="store-announcement-bar {{ $announcementScrollEnabled ? 'is-scrolling' : '' }} py-2 text-center text-xs font-semibold uppercase tracking-wide" style="background-color: {{ $announcementBackgroundColor }}; color: {{ $announcementTextColor }}; --store-announcement-duration: {{ $announcementScrollDuration }}s;">
        @if ($announcementUrl !== '')
            <a href="{{ $announcementUrl }}" class="store-announcement-content hover:underline" @if($announcementNewTab) target="_blank" rel="noopener noreferrer" @endif>
                {{ $announcementText }}
            </a>
        @else
            <span class="store-announcement-content">{{ $announcementText }}</span>
        @endif
    </div>
@endif

<style>
    .store-announcement-bar {
        overflow: hidden;
        white-space: nowrap;
    }

    .store-announcement-content {
        display: inline-block;
        max-width: 100%;
    }

    .store-announcement-bar.is-scrolling {
        text-align: left;
    }

    .store-announcement-bar.is-scrolling .store-announcement-content {
        max-width: none;
        min-width: max-content;
        padding-left: 100%;
        animation: store-announcement-marquee var(--store-announcement-duration, 18s) linear infinite;
        will-change: transform;
    }

    @keyframes store-announcement-marquee {
        from {
            transform: translateX(0);
        }

        to {
            transform: translateX(-100%);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .store-announcement-bar.is-scrolling {
            text-align: center;
        }

        .store-announcement-bar.is-scrolling .store-announcement-content {
            padding-left: 0;
            animation: none;
        }
    }
</style>
