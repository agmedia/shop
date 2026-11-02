<?php

namespace App\Support;

use App\Models\Catalog\Option\OptionValue;
use Illuminate\Support\Str;

final class ProductColorSwatch
{
    public static function style(OptionValue $value): string
    {
        // Color identity is independent of the currently translated label.
        // Prefer the catalog code; opaque codes may use a known source label.
        $candidates = [(string) $value->code, ...$value->translations->pluck('name')->all()];
        $colors = [
            'crna' => '#050505', 'black' => '#050505', 'schwarz' => '#050505',
            'bijela' => '#fcfcfc', 'white' => '#fcfcfc', 'weiss' => '#fcfcfc',
            'crvena' => '#d62828', 'red' => '#d62828', 'rot' => '#d62828',
            'siva' => '#8f96a3', 'gray' => '#8f96a3', 'grey' => '#8f96a3', 'grau' => '#8f96a3',
            'tamno plava' => '#1e3a8a', 'dark blue' => '#1e3a8a', 'navy' => '#1e3a8a',
            'navy blue' => '#1e3a8a', 'marineblau' => '#1e3a8a',
            'boja koze' => '#d2a181', 'nude' => '#d2a181', 'skin' => '#d2a181',
            'hautfarbe' => '#d2a181', 'hautfarben' => '#d2a181',
        ];

        foreach ($candidates as $candidate) {
            $key = Str::lower(Str::ascii(trim((string) $candidate)));
            $key = trim((string) preg_replace('/[\s_-]+/', ' ', $key));
            if (isset($colors[$key])) {
                return 'background:'.$colors[$key].';';
            }
        }

        // A hash-derived color or invented fabric pattern misrepresents the
        // product. Callers can show an unknown marker when no image is supplied.
        return '';
    }
}
