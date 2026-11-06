<?php

namespace App\Console\Commands;

use App\Services\Settings\SystemSettingsService;
use Illuminate\Console\Command;

class ConfigureGermanNewsletter extends Command
{
    protected $signature = 'klaviyo:configure-german-newsletter {--force : Replace a different German list ID already configured}';

    protected $description = 'Configure the client-supplied German newsletter list without changing the default list';

    public function handle(SystemSettingsService $settings): int
    {
        $key = 'store_newsletter_klaviyo_list_id_de';
        $listId = 'V3EEiA';
        $current = trim((string) $settings->get($key, ''));

        if ($current !== '' && $current !== $listId && ! $this->option('force')) {
            $this->warn('Preserved the existing German list ID. Use --force to replace it with the client-supplied list.');

            return self::SUCCESS;
        }

        if ($current !== $listId) {
            $settings->put($key, $listId);
        }

        $this->info('German newsletter list configured: '.$listId);

        return self::SUCCESS;
    }
}
