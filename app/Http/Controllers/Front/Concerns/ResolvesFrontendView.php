<?php

namespace App\Http\Controllers\Front\Concerns;

use Illuminate\Http\Request;

trait ResolvesFrontendView
{
    protected function frontendVariant(Request $request): string
    {
        return (string) $request->attributes->get('frontend_variant', 'desktop') === 'mobile'
            ? 'mobile'
            : 'desktop';
    }

    protected function frontendView(Request $request, string $view): string
    {
        return 'front.'.$this->frontendVariant($request).'.'.$view;
    }
}
