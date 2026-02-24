<?php

namespace Database\Seeders;

use App\Models\Settings\Local\Currency;
use App\Models\Settings\Local\GeoZone;
use App\Models\Settings\Local\GeoZoneCountry;
use App\Models\Settings\Local\Language;
use App\Models\Settings\Local\OrderStatus;
use App\Models\Settings\Local\PaymentMethod;
use App\Models\Settings\Local\Region;
use App\Models\Settings\Local\ShippingMethod;
use App\Models\Settings\Local\TaxRate;
use App\Support\CountryCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class SettingsLocalSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRegions();

        $hr = GeoZone::updateOrCreate(
            ['code' => 'HR'],
            ['name' => 'Croatia', 'description' => 'Domestic zone', 'is_active' => true, 'sort_order' => 1]
        );

        $eu = GeoZone::updateOrCreate(
            ['code' => 'EU'],
            ['name' => 'European Union', 'description' => 'EU shipping/payment zone', 'is_active' => true, 'sort_order' => 2]
        );

        $world = GeoZone::updateOrCreate(
            ['code' => 'WORLD'],
            ['name' => 'World', 'description' => 'Rest of world', 'is_active' => true, 'sort_order' => 3]
        );

        $euCodes = array_values(array_filter(
            CountryCatalog::euCodes(),
            static fn (string $code): bool => $code !== 'HR'
        ));
        $allCodes = CountryCatalog::codes();
        $worldCodes = array_values(array_diff($allCodes, array_merge(['HR'], CountryCatalog::euCodes())));

        GeoZoneCountry::query()
            ->whereIn('geo_zone_id', [$hr->id, $eu->id, $world->id])
            ->delete();

        $now = Carbon::now();
        $payload = [];

        $payload[] = [
            'geo_zone_id' => $hr->id,
            'country_code' => 'HR',
            'region_code' => null,
            'postal_code_from' => null,
            'postal_code_to' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        foreach ($euCodes as $code) {
            $payload[] = [
                'geo_zone_id' => $eu->id,
                'country_code' => $code,
                'region_code' => null,
                'postal_code_from' => null,
                'postal_code_to' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ($worldCodes as $code) {
            $payload[] = [
                'geo_zone_id' => $world->id,
                'country_code' => $code,
                'region_code' => null,
                'postal_code_from' => null,
                'postal_code_to' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        GeoZoneCountry::query()->insert($payload);

        Currency::updateOrCreate(
            ['code' => 'EUR'],
            [
                'name' => 'Euro',
                'symbol' => 'EUR',
                'symbol_position' => 'left',
                'decimal_places' => 2,
                'exchange_rate' => 1,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        Currency::updateOrCreate(
            ['code' => 'USD'],
            [
                'name' => 'US Dollar',
                'symbol' => '$',
                'symbol_position' => 'left',
                'decimal_places' => 2,
                'exchange_rate' => 1.08,
                'is_default' => false,
                'is_active' => false,
                'sort_order' => 2,
            ]
        );

        TaxRate::updateOrCreate(
            ['code' => 'PDV25'],
            [
                'name' => 'PDV 25%',
                'geo_zone_id' => $hr->id,
                'rate_type' => 'percent',
                'rate' => 25,
                'priority' => 1,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        OrderStatus::updateOrCreate(
            ['code' => 'new'],
            ['name' => 'New', 'color' => 'blue', 'is_default' => true, 'is_paid' => false, 'is_cancelled' => false, 'is_active' => true, 'sort_order' => 1]
        );
        OrderStatus::updateOrCreate(
            ['code' => 'paid'],
            ['name' => 'Paid', 'color' => 'emerald', 'is_default' => false, 'is_paid' => true, 'is_cancelled' => false, 'is_active' => true, 'sort_order' => 2]
        );
        OrderStatus::updateOrCreate(
            ['code' => 'sent'],
            ['name' => 'Sent', 'color' => 'violet', 'is_default' => false, 'is_paid' => true, 'is_cancelled' => false, 'is_active' => true, 'sort_order' => 3]
        );
        OrderStatus::updateOrCreate(
            ['code' => 'cancelled'],
            ['name' => 'Cancelled', 'color' => 'rose', 'is_default' => false, 'is_paid' => false, 'is_cancelled' => true, 'is_active' => true, 'sort_order' => 4]
        );

        Language::updateOrCreate(
            ['code' => 'hr'],
            ['locale' => 'hr_HR', 'name' => 'Croatian', 'native_name' => 'Hrvatski', 'direction' => 'ltr', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]
        );
        Language::updateOrCreate(
            ['code' => 'en'],
            ['locale' => 'en_US', 'name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_default' => false, 'is_active' => true, 'sort_order' => 2]
        );

        PaymentMethod::updateOrCreate(
            ['code' => 'cod'],
            [
                'name' => 'Cash on Delivery',
                'provider' => 'cod',
                'geo_zone_id' => $hr->id,
                'description' => 'Pay in cash when receiving the package.',
                'fee_type' => 'fixed',
                'fee_value' => 0,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
        PaymentMethod::updateOrCreate(
            ['code' => 'bank'],
            [
                'name' => 'Bank Transfer',
                'provider' => 'bank',
                'geo_zone_id' => null,
                'description' => 'Manual transfer by bank wire.',
                'fee_type' => 'fixed',
                'fee_value' => 0,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );
        PaymentMethod::updateOrCreate(
            ['code' => 'pickup'],
            [
                'name' => 'Pay on Pickup',
                'provider' => 'pickup',
                'geo_zone_id' => $hr->id,
                'description' => 'Payment in store pickup location.',
                'fee_type' => 'fixed',
                'fee_value' => 0,
                'is_active' => true,
                'sort_order' => 3,
            ]
        );
        PaymentMethod::updateOrCreate(
            ['code' => 'wspay'],
            [
                'name' => 'WSPay',
                'provider' => 'wspay',
                'geo_zone_id' => null,
                'description' => 'Card payment via WSPay form redirect.',
                'fee_type' => 'fixed',
                'fee_value' => 0,
                'is_active' => false,
                'sort_order' => 4,
                'settings' => [
                    'wspay_mode' => 'test',
                    'wspay_form_url' => 'https://formtest.wspay.biz/authorization.aspx',
                    'wspay_return_method' => 'GET',
                ],
            ]
        );
        PaymentMethod::updateOrCreate(
            ['code' => 'corvus'],
            [
                'name' => 'CorvusPay',
                'provider' => 'corvuspay',
                'geo_zone_id' => null,
                'description' => 'Card payment via CorvusPay form redirect.',
                'fee_type' => 'fixed',
                'fee_value' => 0,
                'is_active' => false,
                'sort_order' => 5,
                'settings' => [
                    'corvus_mode' => 'test',
                    'corvus_form_url' => 'https://wallet.test.corvuspay.com/checkout/',
                    'corvus_language' => 'hr',
                    'corvus_currency' => 'EUR',
                    'corvus_require_complete' => 'false',
                ],
            ]
        );
        PaymentMethod::updateOrCreate(
            ['code' => 'keks'],
            [
                'name' => 'KEKS Pay',
                'provider' => 'kekspay',
                'geo_zone_id' => null,
                'description' => 'Najbrže i bez naknada putem KEKS Pay aplikacije!',
                'fee_type' => 'fixed',
                'fee_value' => 0,
                'is_active' => false,
                'sort_order' => 6,
                'settings' => [
                    'keks_mode' => 'test',
                    'keks_qr_type' => 1,
                    'keks_sell_base_url' => 'https://kekspayuat.erstebank.hr/galebpay',
                    'keks_advice_auth_mode' => 'none',
                ],
            ]
        );

        ShippingMethod::updateOrCreate(
            ['code' => 'standard'],
            [
                'name' => 'Standard Shipping',
                'geo_zone_id' => $hr->id,
                'description' => 'Standard home delivery.',
                'price' => 4.99,
                'free_over' => 60,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
        ShippingMethod::updateOrCreate(
            ['code' => 'standard_eu'],
            [
                'name' => 'Standard Shipping (EU)',
                'geo_zone_id' => $eu->id,
                'description' => 'Standard shipping for EU countries (excluding Croatia).',
                'price' => 9.99,
                'free_over' => 120,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );
        ShippingMethod::updateOrCreate(
            ['code' => 'standard_world'],
            [
                'name' => 'Standard Shipping (World)',
                'geo_zone_id' => $world->id,
                'description' => 'Standard international shipping outside EU.',
                'price' => 19.99,
                'free_over' => 200,
                'is_active' => true,
                'sort_order' => 3,
            ]
        );
        ShippingMethod::updateOrCreate(
            ['code' => 'pickup'],
            [
                'name' => 'Store Pickup',
                'geo_zone_id' => $hr->id,
                'description' => 'Pickup in store.',
                'price' => 0,
                'free_over' => null,
                'is_active' => true,
                'sort_order' => 4,
            ]
        );
        ShippingMethod::updateOrCreate(
            ['code' => 'express'],
            [
                'name' => 'Express Shipping',
                'geo_zone_id' => $hr->id,
                'description' => 'Priority next-day delivery.',
                'price' => 8.99,
                'free_over' => null,
                'is_active' => true,
                'sort_order' => 5,
            ]
        );
        ShippingMethod::updateOrCreate(
            ['code' => 'boxnow'],
            [
                'name' => 'BOX NOW Locker',
                'geo_zone_id' => $hr->id,
                'description' => 'BOX NOW paketomat dostava.',
                'price' => 2.99,
                'free_over' => null,
                'is_active' => true,
                'sort_order' => 6,
                'settings' => [
                    'boxnow_partner_id' => '',
                ],
            ]
        );
    }

    private function seedRegions(): void
    {
        $path = public_path('front-theme/data/hr-places.json');
        if (!File::exists($path)) {
            return;
        }

        $raw = json_decode((string) File::get($path), true);
        if (!is_array($raw)) {
            return;
        }

        $counties = array_values(array_filter((array) ($raw['counties_hr'] ?? []), 'is_string'));
        if ($counties === []) {
            return;
        }

        Region::query()->where('country_code', 'HR')->delete();

        $payload = [];
        $now = Carbon::now();
        foreach (array_values($counties) as $index => $county) {
            $name = trim($county);
            if ($name === '') {
                continue;
            }

            $payload[] = [
                'country_code' => 'HR',
                'code' => strtoupper(str_replace(' ', '_', $name)),
                'name' => $name,
                'is_active' => true,
                'sort_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($payload !== []) {
            Region::query()->insert($payload);
        }
    }
}
