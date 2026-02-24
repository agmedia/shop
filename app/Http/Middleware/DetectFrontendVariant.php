<?php

namespace App\Http\Middleware;

use App\Services\Catalog\CatalogFeatureService;
use App\Services\Front\DeviceViewResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class DetectFrontendVariant
{
    public function __construct(
        private readonly DeviceViewResolver $deviceViewResolver,
        private readonly CatalogFeatureService $catalogFeatureService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $variant = $this->deviceViewResolver->variant($request->userAgent());

        $requestedVariant = (string) $request->query('frontend_variant', '');
        $user = $request->user();
        $canForceVariant = $user && ($user->isA('superadmin') || $user->can('content.blocks'));

        if ($canForceVariant && in_array($requestedVariant, ['desktop', 'mobile'], true)) {
            $variant = $requestedVariant;
        }

        $useMobilePwa = (bool) config('catalog_features.flags.catalog_use_mobile_pwa', true);

        try {
            $useMobilePwa = $this->catalogFeatureService->useMobilePwa();
        } catch (\Throwable) {
            // Fallback to config when settings storage isn't available yet (e.g. isolated tests).
        }

        if (! $useMobilePwa) {
            $variant = 'desktop';
        }

        $request->attributes->set('frontend_variant', $variant);
        View::share('frontendVariant', $variant);

        $response = $next($request);

        $existing = (string) $response->headers->get('Vary', '');
        $varyParts = collect(explode(',', $existing))
            ->map(static fn ($item) => trim((string) $item))
            ->filter();

        if (! $varyParts->contains(static fn ($item) => strcasecmp((string) $item, 'User-Agent') === 0)) {
            $varyParts->push('User-Agent');
        }

        $response->headers->set('Vary', $varyParts->implode(', '));

        return $response;
    }
}
