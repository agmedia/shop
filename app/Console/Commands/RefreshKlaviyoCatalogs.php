<?php

namespace App\Console\Commands;

use App\Services\Integrations\Klaviyo\KlaviyoCatalogService;
use Illuminate\Console\Command;

class RefreshKlaviyoCatalogs extends Command
{
    protected $signature = 'klaviyo:refresh-catalogs';

    protected $description = 'Refresh the public German and Austrian Klaviyo catalogs from the live shop database';

    public function handle(KlaviyoCatalogService $catalog): int
    {
        foreach ($catalog->refresh() as $country => $count) {
            $this->info($country.': '.$count.' products refreshed.');
        }

        return self::SUCCESS;
    }
}
