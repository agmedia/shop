<?php

namespace App\Services\Front;

final class LegalPageLinkService
{
    public function render(string $html, string $locale): string
    {
        if ($locale !== 'de' || ! str_contains($html, 'FUNKTIONSLINK')) {
            return $html;
        }

        return preg_replace_callback(
            '/\[\s*FUNKTIONSLINK\s*[-–—]\s*(Vertrag\s+widerrufen|Retourenportal|Cookie-Einstellungen)\s*\]/u',
            static function (array $match): string {
                $label = preg_replace('/\s+/u', ' ', trim($match[1]));
                if ($label === 'Cookie-Einstellungen') {
                    return '<button type="button" data-cookie-consent-preferences class="underline underline-offset-2" style="font:inherit;color:inherit;cursor:pointer;">Cookie-Einstellungen</button>';
                }

                $parameters = ['returnRequestSlug' => 'rucksendungen-und-reklamationen', 'lang' => 'de'];
                if ($label === 'Vertrag widerrufen') {
                    $parameters['withdrawal'] = 1;
                }

                return '<a href="'.e(route('returns.create', $parameters)).'" class="underline underline-offset-2">'.e($label).'</a>';
            },
            $html
        ) ?? $html;
    }
}
