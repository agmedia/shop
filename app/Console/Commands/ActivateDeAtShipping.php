<?php

namespace App\Console\Commands;

use App\Models\Settings\Local\GeoZone;
use App\Models\Settings\Local\GeoZoneCountry;
use App\Models\Settings\Local\ShippingMethod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use LogicException;

class ActivateDeAtShipping extends Command
{
    protected $signature = 'storefront:activate-de-at-shipping';

    protected $description = 'Enable existing standard_eu delivery for DE/AT: EUR 10, free from EUR 80 in discounted gross goods';

    public function handle(): int
    {
        $method = ShippingMethod::query()->where('code', 'standard_eu')->first();
        if (! $method) {
            $this->error('The existing standard_eu shipping method was not found. No settings changed.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($method): void {
                $zone = GeoZone::query()->firstOrCreate(['code' => 'DE_AT'], [
                    'name' => 'Germany and Austria (DE/AT)',
                    'description' => 'Bali storefront delivery to Germany and Austria.',
                    'is_active' => true,
                    'sort_order' => 2,
                ]);
                if ($zone->countries()->whereNotIn('country_code', ['DE', 'AT'])->exists()) {
                    throw new LogicException('The DE_AT geo zone contains other countries. No settings changed.');
                }
                $zone->forceFill(['is_active' => true])->save();
                foreach (['DE', 'AT'] as $country) {
                    GeoZoneCountry::query()->firstOrCreate([
                        'geo_zone_id' => $zone->id,
                        'country_code' => $country,
                        'region_code' => null,
                        'postal_code_from' => null,
                        'postal_code_to' => null,
                    ]);
                }

                $settings = (array) ($method->settings ?? []);
                $settings['free_over_basis'] = 'gross';
                $method->forceFill([
                    'geo_zone_id' => $zone->id,
                    'price' => 10,
                    'free_over' => 80,
                    'is_active' => true,
                    'settings' => $settings,
                ])->save();
            });
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('DE/AT standard_eu delivery enabled: EUR 10.00; free from EUR 80.00 in discounted gross goods, excluding delivery and payment fees.');

        return self::SUCCESS;
    }
}
