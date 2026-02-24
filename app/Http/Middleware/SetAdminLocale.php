<?php

namespace App\Http\Middleware;

use App\Models\Settings\Local\Language;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = $this->availableLocales();
        $fallback = $this->normalizeLocale((string) config('app.locale', 'en')) ?: 'en';

        $queryLocale = $this->normalizeLocale((string) $request->query('admin_locale', ''));
        $sessionLocale = $this->normalizeLocale((string) $request->session()->get('admin_locale', ''));

        $locale = in_array($queryLocale, $available, true)
            ? $queryLocale
            : (in_array($sessionLocale, $available, true) ? $sessionLocale : (in_array($fallback, $available, true) ? $fallback : (string) ($available[0] ?? $fallback)));

        App::setLocale($locale);
        $request->setLocale($locale);
        $request->session()->put('admin_locale', $locale);

        return $next($request);
    }

    /**
     * @return array<int, string>
     */
    private function availableLocales(): array
    {
        $fallback = $this->normalizeLocale((string) config('app.locale', 'en')) ?: 'en';
        $defaults = ['hr', 'en'];

        try {
            $codes = Language::query()
                ->where('is_active', true)
                ->pluck('code')
                ->map(fn ($code) => $this->normalizeLocale((string) $code))
                ->filter(fn ($code) => $code !== '')
                ->unique()
                ->values()
                ->all();

            $merged = array_values(array_unique(array_filter([
                ...$defaults,
                ...$codes,
                $fallback,
            ])));

            return $merged !== [] ? $merged : $defaults;
        } catch (\Throwable) {
            return array_values(array_unique([$defaults[0], $defaults[1], $fallback]));
        }
    }

    private function normalizeLocale(string $locale): string
    {
        $normalized = strtolower(trim($locale));
        if ($normalized === '') {
            return '';
        }

        if (str_contains($normalized, '_')) {
            $normalized = (string) explode('_', $normalized)[0];
        }

        if (str_contains($normalized, '-')) {
            $normalized = (string) explode('-', $normalized)[0];
        }

        return $normalized;
    }
}
