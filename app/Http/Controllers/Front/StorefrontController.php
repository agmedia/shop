<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Services\Front\StoreSettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly StoreSettingsService $storeSettingsService
    ) {
    }

    public function home(Request $request): View
    {
        $variant = (string) $request->attributes->get('frontend_variant', 'desktop');

        return view(
            $variant === 'mobile' ? 'welcome-mobile' : 'front.desktop.home.index',
            ['storeSettings' => $this->storeSettingsService->all()]
        );
    }

    public function manifest(): Response
    {
        $settings = $this->storeSettingsService->all();
        $branding = $settings['branding'] ?? [];
        $favicons = $branding['favicons'] ?? [];

        $name = trim((string) ($branding['store_name'] ?? config('app.name', 'AG Shop')));
        if ($name === '') {
            $name = (string) config('app.name', 'AG Shop');
        }

        $icon192 = (string) ($favicons['192_url'] ?? '');
        $icon512 = (string) ($favicons['512_url'] ?? '');

        if ($icon192 === '') {
            $icon192 = asset('front-theme/app/icons/icon-192x192.png');
        }
        if ($icon512 === '') {
            $icon512 = asset('front-theme/app/icons/icon-512x512.png');
        }

        $payload = [
            'name' => $name,
            'short_name' => $name,
            'start_url' => route('home'),
            'scope' => url('/'),
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#111827',
            'icons' => [
                [
                    'src' => $icon192,
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
                [
                    'src' => $icon512,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
        ];

        return response(
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            200,
            ['Content-Type' => 'application/manifest+json']
        );
    }
}
