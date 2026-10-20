<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Services\Integrations\Klaviyo\KlaviyoCatalogService;
use Illuminate\Http\JsonResponse;

class KlaviyoCatalogController extends Controller
{
    public function __invoke(string $country, KlaviyoCatalogService $catalog): JsonResponse
    {
        return response()->json($catalog->cachedItems(strtoupper($country)))
            ->header('Cache-Control', 'no-store');
    }
}
