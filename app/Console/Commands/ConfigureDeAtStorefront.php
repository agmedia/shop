<?php

namespace App\Console\Commands;

use App\Models\Settings\Local\Language;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Console\Command;

class ConfigureDeAtStorefront extends Command
{
    protected $signature = 'storefront:configure-de-at';

    protected $description = 'Enable German storefront, the client GA4 property, purchase events and the consent banner';

    public function handle(SystemSettingsService $settings): int
    {
        Language::query()->updateOrCreate(['code' => 'de'], [
            'name' => 'German', 'native_name' => 'Deutsch', 'is_active' => true,
        ]);
        $settings->putMany([
            'store_analytics_enabled' => true,
            'store_analytics_ga4_measurement_id' => 'G-DT810P4TK9',
            'store_analytics_purchase_event_enabled' => true,
            'store_analytics_purchase_event_name' => 'purchase',
            'store_cookie_consent_enabled' => true,
        ]);
        $germanCookieCopy = [
            'store_cookie_consent_title' => 'Wir verwenden Cookies',
            'store_cookie_consent_message' => 'Wir verwenden notwendige Cookies für den Betrieb des Shops. Analyse- und Marketing-Technologien werden nur mit Ihrer Einwilligung aktiviert. Sie können Ihre Auswahl jederzeit ändern.',
            'store_cookie_consent_accept_label' => 'Alle akzeptieren',
            'store_cookie_consent_policy_label' => 'Datenschutz und Cookies',
            'store_cookie_preferences_title' => 'Cookie-Einstellungen',
            'store_cookie_preferences_accept_all_label' => 'Alle akzeptieren',
            'store_cookie_preferences_accept_necessary_label' => 'Nur notwendige',
            'store_cookie_preferences_save_label' => 'Auswahl speichern',
            'store_cookie_necessary_title' => 'Notwendige Cookies',
            'store_cookie_necessary_description' => 'Diese Cookies sind für Warenkorb, Anmeldung, Sicherheit und den Betrieb des Shops erforderlich.',
            'store_cookie_analytics_title' => 'Analyse-Cookies',
            'store_cookie_analytics_description' => 'Diese Technologien helfen uns, die Nutzung unseres Shops zu verstehen. Sie werden nur mit Ihrer Einwilligung aktiviert.',
            'store_cookie_marketing_title' => 'Marketing-Cookies',
            'store_cookie_marketing_description' => 'Diese Technologien dienen der Personalisierung von Werbung und werden nur mit Ihrer Einwilligung aktiviert.',
        ];
        foreach ($germanCookieCopy as $key => $text) {
            $translations = (array) $settings->get($key.'_translations', []);
            if (empty($translations['de'])) {
                $translations['de'] = $text;
                $settings->put($key.'_translations', $translations);
            }
        }
        $this->info('German storefront and consent-gated GA4 configured.');

        return self::SUCCESS;
    }
}
