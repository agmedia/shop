<?php

declare(strict_types=1);

use App\Services\Integrations\Kipos\KiposSdkService;
use App\Services\Integrations\Kipos\KiposSyncService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;

@set_time_limit(0);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

/**
 * @param  array<string, mixed>  $payload
 */
function kipos_quantities_respond(array $payload, int $status = 200): never
{
    if (PHP_SAPI !== 'cli') {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
    }

    echo json_encode(
        $payload,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
    ).PHP_EOL;

    exit;
}

/**
 * @param  array<string, mixed>  $row
 */
function kipos_quantities_string(array $row, string $key): string
{
    return array_key_exists($key, $row) ? strtoupper(trim((string) $row[$key])) : '';
}

/**
 * @param  array<string, mixed>  $row
 */
function kipos_quantities_number(array $row, string $key): float
{
    if (! array_key_exists($key, $row)) {
        return 0.0;
    }

    if (is_numeric($row[$key])) {
        return (float) $row[$key];
    }

    $value = str_replace([' ', "\xc2\xa0"], '', trim((string) $row[$key]));
    if (str_contains($value, ',') && str_contains($value, '.')) {
        $value = str_replace('.', '', $value);
    }

    $value = str_replace(',', '.', $value);

    return is_numeric($value) ? (float) $value : 0.0;
}

if (PHP_SAPI !== 'cli') {
    $expectedToken = trim((string) config('services.kipos.cron_token', ''));
    if ($expectedToken === '') {
        kipos_quantities_respond(['ok' => false, 'error' => 'Not found.'], 404);
    }

    $providedToken = trim((string) ($_GET['token'] ?? ($_SERVER['HTTP_X_KIPOS_CRON_TOKEN'] ?? '')));
    if ($providedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
        kipos_quantities_respond(['ok' => false, 'error' => 'Forbidden.'], 403);
    }
}

$kipos = app(KiposSdkService::class);
$syncSettings = app(KiposSyncService::class)->syncSettings();
$warehouses = array_values(array_unique(array_filter(array_map(
    static fn (string $warehouse): string => strtoupper(trim($warehouse)),
    explode(',', (string) ($syncSettings['kipos_sync_stock_warehouse_ids'] ?? '200'))
))));

if ($warehouses === []) {
    $warehouses = ['200'];
}

$requestedCode = strtoupper(trim((string) ($_GET['sifra'] ?? '')));
$items = [];

try {
    $kipos->assertEnabled();

    foreach ($warehouses as $warehouse) {
        $rows = $kipos->getRows('sif_roba/getZalihaK', [
            'webshop' => 2,
            'idskl' => $warehouse,
        ]);

        foreach ($rows as $row) {
            $code = kipos_quantities_string($row, 'IDROBA');
            if ($code === '' || ($requestedCode !== '' && ! str_contains($code, $requestedCode))) {
                continue;
            }

            $rawQuantity = kipos_quantities_number($row, 'ZALIHAK');
            $items[$code] ??= [
                'sifra' => $code,
                'kolicina' => 0.0,
                'kolicina_za_sync' => 0,
            ];
            $items[$code]['kolicina'] += $rawQuantity;
            $items[$code]['kolicina_za_sync'] += max(0, (int) round($rawQuantity));
        }
    }
} catch (Throwable $exception) {
    report($exception);

    kipos_quantities_respond([
        'ok' => false,
        'error' => $exception->getMessage(),
    ], 502);
}

uksort($items, 'strnatcasecmp');

kipos_quantities_respond([
    'ok' => true,
    'webshop' => 2,
    'skladista' => $warehouses,
    'broj_artikala' => count($items),
    'artikli' => array_values($items),
]);
