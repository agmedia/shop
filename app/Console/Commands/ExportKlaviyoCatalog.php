<?php

namespace App\Console\Commands;

use App\Services\Integrations\Klaviyo\KlaviyoCatalogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class ExportKlaviyoCatalog extends Command
{
    protected $signature = 'klaviyo:export-catalog {--locale=de} {--country=DE} {--base-url=} {--output=storage/app/exports/klaviyo-catalog.json} {--allow-missing-local-images : Compatibility option; public image URLs do not require local copies}';

    protected $description = 'Save the dynamic Klaviyo catalog as a one-time JSON file';

    public function handle(KlaviyoCatalogService $catalog): int
    {
        try {
            $items = $catalog->items(
                (string) $this->option('locale'),
                strtoupper((string) $this->option('country')),
                (string) ($this->option('base-url') ?: config('storefront_compliance.catalog_base_url', 'https://bali.hr')),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $output = (string) $this->option('output');
        if (! str_starts_with($output, '/')) {
            $output = base_path($output);
        }
        File::ensureDirectoryExists(dirname($output));
        File::put($output, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $this->info('Exported '.count($items).' products to '.$output);

        return self::SUCCESS;
    }
}
