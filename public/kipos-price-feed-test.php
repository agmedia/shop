<?php

declare(strict_types=1);

use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductOptionValue;
use App\Services\Integrations\Kipos\KiposSdkService;
use App\Services\Integrations\Kipos\KiposSyncService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;

@set_time_limit(0);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

/*
 * Temporary diagnostic endpoint. Remove this file when price debugging is done.
 * The plain-text access token is intentionally not stored in this file.
 */
const KIPOS_PRICE_FEED_TEST_TOKEN_HASH = 'c5d3fc2d97fcb628cf001242c686830cbeee6f2ba85d3e8de3b7e557d14ab397';

if (PHP_SAPI !== 'cli') {
    $token = (string) ($_GET['token'] ?? '');

    if ($token === '' || ! hash_equals(KIPOS_PRICE_FEED_TEST_TOKEN_HASH, hash('sha256', $token))) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        echo "Forbidden\n";
        exit;
    }
}

/**
 * @param  array<string, mixed>  $row
 */
function kipos_price_test_string(array $row, string ...$keys): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row)) {
            return trim((string) $row[$key]);
        }
    }

    return '';
}

/**
 * Same number normalization as KiposSyncService::floatValue().
 *
 * @param  array<string, mixed>  $row
 */
function kipos_price_test_float(array $row, string ...$keys): float
{
    foreach ($keys as $key) {
        if (! array_key_exists($key, $row)) {
            continue;
        }

        $value = $row[$key];
        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = trim((string) $value);
        $normalized = str_replace([' ', "\xc2\xa0"], '', $normalized);

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
        }

        $normalized = str_replace(',', '.', $normalized);
        if (is_numeric($normalized)) {
            return (float) $normalized;
        }
    }

    return 0.0;
}

/**
 * @param  array<string, mixed>  $row
 */
function kipos_price_test_item_code(array $row): string
{
    return strtoupper(kipos_price_test_string($row, 'IDROBA'));
}

/**
 * Same grouping key as KiposSyncService::departmentCode().
 *
 * @param  array<string, mixed>  $row
 */
function kipos_price_test_department_code(array $row): string
{
    $department = strtoupper(kipos_price_test_string($row, 'IDODJEL'));
    if ($department !== '') {
        return $department;
    }

    $itemCode = kipos_price_test_item_code($row);
    $dotPosition = strrpos($itemCode, '.');

    return $dotPosition === false ? $itemCode : substr($itemCode, 0, $dotPosition);
}

/**
 * Same selected-price and fallback logic as KiposSyncService::rowPrice().
 *
 * @param  array<string, mixed>  $row
 */
function kipos_price_test_row_price(array $row, string $priceField): float
{
    $price = kipos_price_test_float($row, $priceField);
    if ($price > 0) {
        return round($price, 2);
    }

    foreach (['CIJENA_MPC', 'CIJENA_EUR_MPC', 'CIJENA_EUR'] as $fallbackKey) {
        $fallback = kipos_price_test_float($row, $fallbackKey);
        if ($fallback > 0) {
            return round($fallback, 2);
        }
    }

    return 0.0;
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @return array<string, list<array<string, mixed>>>
 */
function kipos_price_test_groups(array $rows): array
{
    $groups = [];

    foreach ($rows as $row) {
        $groupCode = kipos_price_test_department_code($row);
        if ($groupCode === '') {
            continue;
        }

        $groups[$groupCode] ??= [];
        $groups[$groupCode][] = $row;
    }

    return $groups;
}

/**
 * Same base-price logic as KiposSyncService::groupBasePrice().
 *
 * @param  list<array<string, mixed>>  $rows
 */
function kipos_price_test_group_base_price(array $rows, string $priceField): float
{
    $prices = [];

    foreach ($rows as $row) {
        $price = kipos_price_test_row_price($row, $priceField);
        if ($price > 0) {
            $prices[] = $price;
        }
    }

    return $prices === [] ? 0.0 : round(min($prices), 2);
}

function kipos_price_test_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function kipos_price_test_format(mixed $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    return number_format((float) $value, 2, ',', '.');
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @return list<array<string, mixed>>
 */
function kipos_price_test_filter(array $rows, string $query): array
{
    $query = trim($query);
    if ($query === '') {
        return $rows;
    }

    return array_values(array_filter($rows, static function (array $row) use ($query): bool {
        $haystack = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $haystack !== false && stripos($haystack, $query) !== false;
    }));
}

/**
 * @param  list<array<string, mixed>>  $rows
 */
function kipos_price_test_sort(array &$rows): void
{
    usort($rows, static function (array $left, array $right): int {
        $departmentCompare = strnatcasecmp(
            kipos_price_test_department_code($left),
            kipos_price_test_department_code($right)
        );

        return $departmentCompare !== 0
            ? $departmentCompare
            : strnatcasecmp(kipos_price_test_item_code($left), kipos_price_test_item_code($right));
    });
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @return list<string>
 */
function kipos_price_test_price_keys(array $rows, string $priceField): array
{
    $keys = [$priceField => true];

    foreach ($rows as $row) {
        foreach (array_keys($row) as $key) {
            $key = strtoupper((string) $key);
            if (str_contains($key, 'CIJENA') || str_contains($key, 'PRICE')) {
                $keys[$key] = true;
            }
        }
    }

    return array_keys($keys);
}

$kipos = app(KiposSdkService::class);
$sync = app(KiposSyncService::class);
$syncSettings = $sync->syncSettings();
$priceField = strtoupper(trim((string) ($syncSettings['kipos_sync_price_field'] ?? 'CIJENA_MPC')));
$query = trim((string) ($_GET['q'] ?? ''));
$limit = max(1, min(2000, (int) ($_GET['limit'] ?? 500)));
$format = strtolower(trim((string) ($_GET['format'] ?? (PHP_SAPI === 'cli' ? 'csv' : 'html'))));
$source = strtolower(trim((string) ($_GET['source'] ?? 'admin')));
$source = $source === 'base' ? 'base' : 'admin';
$route = $source === 'base' ? 'sif_roba/getitems' : 'sif_roba/getitemsextended';
$token = (string) ($_GET['token'] ?? '');

try {
    // Admin Update Prices calls assertEnabled() and sif_roba/getitemsextended.
    $kipos->assertEnabled();
    $allRows = $kipos->getRows($route);
} catch (Throwable $exception) {
    http_response_code(502);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Kipos feed error: '.$exception->getMessage()."\n";
    exit;
}

$groups = kipos_price_test_groups($allRows);
$groupBasePrices = [];
foreach ($groups as $groupCode => $groupRows) {
    $groupBasePrices[$groupCode] = kipos_price_test_group_base_price($groupRows, $priceField);
}

$filteredRows = kipos_price_test_filter($allRows, $query);
kipos_price_test_sort($filteredRows);
$matchedCount = count($filteredRows);
$displayRows = array_slice($filteredRows, 0, $limit);
$priceKeys = kipos_price_test_price_keys($allRows, $priceField);

$displayGroupCodes = array_values(array_unique(array_filter(array_map(
    static fn (array $row): string => kipos_price_test_department_code($row),
    $displayRows
))));

$products = Product::query()
    ->with('optionValues')
    ->whereIn('code', $displayGroupCodes)
    ->get()
    ->keyBy(static fn (Product $product): string => strtoupper((string) $product->code));

/**
 * @return array{product:Product|null,variant:ProductOptionValue|null}
 */
function kipos_price_test_local_match(array $row, $products): array
{
    /** @var Product|null $product */
    $product = $products->get(kipos_price_test_department_code($row));
    /** @var ProductOptionValue|null $variant */
    $variant = $product?->optionValues->first(
        static fn (ProductOptionValue $value): bool => strtoupper((string) $value->sku) === kipos_price_test_item_code($row)
    );

    return ['product' => $product, 'variant' => $variant];
}

if ($format === 'json') {
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    echo json_encode([
        'endpoint' => $route,
        'matches_admin_update_source' => $source === 'admin',
        'price_field' => $priceField,
        'feed_rows' => count($allRows),
        'feed_groups' => count($groups),
        'matched_rows' => $matchedCount,
        'returned_rows' => count($displayRows),
        'rows' => array_map(static function (array $row) use ($groupBasePrices, $priceField): array {
            $groupCode = kipos_price_test_department_code($row);

            return [
                'admin_update_preview' => [
                    'group_code' => $groupCode,
                    'row_price' => kipos_price_test_row_price($row, $priceField),
                    'product_base_price' => $groupBasePrices[$groupCode] ?? 0.0,
                ],
                'raw_kipos_row' => $row,
            ];
        }, $displayRows),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="kipos-price-feed-test.csv"');
    header('X-Robots-Tag: noindex, nofollow, noarchive');

    $out = fopen('php://output', 'wb');
    fputcsv($out, array_merge(
        ['IDROBA', 'IDODJEL', 'NAZIV', 'IDVELICINA', 'ADMIN_ROW_PRICE', 'ADMIN_PRODUCT_BASE_PRICE'],
        $priceKeys
    ));

    foreach ($displayRows as $row) {
        $groupCode = kipos_price_test_department_code($row);
        $line = [
            kipos_price_test_item_code($row),
            $groupCode,
            kipos_price_test_string($row, 'NAZIV', 'NAZIV_ODJELA'),
            kipos_price_test_string($row, 'IDVELICINA'),
            kipos_price_test_row_price($row, $priceField),
            $groupBasePrices[$groupCode] ?? 0.0,
        ];

        foreach ($priceKeys as $key) {
            $line[] = $row[$key] ?? '';
        }

        fputcsv($out, $line);
    }

    fclose($out);
    exit;
}

$baseParams = [
    'token' => $token,
    'q' => $query,
    'limit' => $limit,
    'source' => $source,
];
$csvUrl = '?'.http_build_query([...$baseParams, 'format' => 'csv']);
$jsonUrl = '?'.http_build_query([...$baseParams, 'format' => 'json']);

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

?>
<!doctype html>
<html lang="hr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Kipos price feed test</title>
    <style>
        :root { color-scheme: light; --bg: #f5f7fa; --panel: #fff; --line: #d9e0e8; --text: #172033; --muted: #697386; --accent: #075985; --good: #067647; --warn: #b54708; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 14px/1.45 system-ui, sans-serif; }
        main { width: min(1800px, calc(100vw - 28px)); margin: 24px auto; }
        h1 { margin: 0 0 5px; font-size: 25px; }
        .muted { color: var(--muted); }
        .notice, .summary, .toolbar { margin-top: 14px; padding: 13px; border: 1px solid var(--line); border-radius: 8px; background: var(--panel); }
        .notice { border-color: #fedf89; background: #fffaeb; color: #93370d; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; }
        .label { color: var(--muted); font-size: 11px; text-transform: uppercase; }
        .value { margin-top: 3px; font-weight: 750; }
        .toolbar, form { display: flex; flex-wrap: wrap; align-items: end; gap: 9px; }
        form { flex: 1 1 580px; }
        label { display: grid; gap: 4px; color: var(--muted); font-size: 12px; }
        input, select { min-width: 240px; padding: 8px 9px; border: 1px solid var(--line); border-radius: 6px; background: #fff; font: inherit; color: var(--text); }
        input[type=number] { min-width: 90px; width: 100px; }
        button, a.button { display: inline-block; padding: 8px 11px; border: 1px solid var(--accent); border-radius: 6px; background: var(--accent); color: #fff; font-weight: 700; text-decoration: none; cursor: pointer; }
        a.secondary { border-color: var(--line); background: #fff; color: var(--text); }
        .table-wrap { margin-top: 14px; overflow: auto; border: 1px solid var(--line); border-radius: 8px; background: var(--panel); }
        table { width: 100%; min-width: 1500px; border-collapse: collapse; }
        th, td { padding: 8px 9px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; white-space: nowrap; }
        th { position: sticky; top: 0; z-index: 1; background: #eaf0f6; color: #344054; font-size: 11px; text-transform: uppercase; }
        tr:nth-child(even) td { background: #fafbfd; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .calculated { font-weight: 800; color: var(--accent); }
        .same { color: var(--good); }
        .different { color: var(--warn); font-weight: 800; }
        code { font-size: 12px; }
    </style>
</head>
<body>
<main>
    <h1>Kipos price feed test</h1>
    <div class="muted">Read-only pregled Kipos cijena i logike koju koristi admin “Update Prices”.</div>

    <div class="notice">
        Ovaj fajl ništa ne upisuje u bazu. Nakon dijagnostike ukloni ga iz <code>public/</code> direktorija.
    </div>

    <section class="summary">
        <div><div class="label">Kipos endpoint</div><div class="value"><?= kipos_price_test_escape($route) ?></div></div>
        <div><div class="label">Admin koristi ovaj feed</div><div class="value"><?= $source === 'admin' ? 'DA' : 'NE — usporedni feed' ?></div></div>
        <div><div class="label">Odabrano polje</div><div class="value"><?= kipos_price_test_escape($priceField) ?></div></div>
        <div><div class="label">Feed redova</div><div class="value"><?= count($allRows) ?></div></div>
        <div><div class="label">Grupa / proizvoda</div><div class="value"><?= count($groups) ?></div></div>
        <div><div class="label">Pronađeno</div><div class="value"><?= $matchedCount ?></div></div>
        <div><div class="label">Prikazano</div><div class="value"><?= count($displayRows) ?> / <?= $limit ?></div></div>
        <div><div class="label">Vrijeme</div><div class="value"><?= kipos_price_test_escape(now()->format('Y-m-d H:i:s')) ?></div></div>
    </section>

    <section class="toolbar">
        <form method="get">
            <input type="hidden" name="token" value="<?= kipos_price_test_escape($token) ?>">
            <label>
                Izvor
                <select name="source">
                    <option value="admin" <?= $source === 'admin' ? 'selected' : '' ?>>Admin update — getitemsextended</option>
                    <option value="base" <?= $source === 'base' ? 'selected' : '' ?>>Base usporedba — getitems</option>
                </select>
            </label>
            <label>
                Šifra ili naziv
                <input type="search" name="q" value="<?= kipos_price_test_escape($query) ?>" placeholder="npr. 1048NC">
            </label>
            <label>
                Maks. redova
                <input type="number" name="limit" min="1" max="2000" value="<?= $limit ?>">
            </label>
            <button type="submit">Prikaži</button>
        </form>
        <a class="button secondary" href="<?= kipos_price_test_escape($csvUrl) ?>">CSV</a>
        <a class="button secondary" href="<?= kipos_price_test_escape($jsonUrl) ?>">Raw JSON</a>
    </section>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>IDROBA</th>
                <th>IDODJEL</th>
                <th>Naziv</th>
                <th>Veličina</th>
                <th class="num">Raw <?= kipos_price_test_escape($priceField) ?></th>
                <th class="num">Admin row price</th>
                <th class="num">Admin base price</th>
                <th class="num">Shop base sada</th>
                <th class="num">Shop varijanta sada</th>
                <th>Match</th>
                <?php foreach ($priceKeys as $key): ?>
                    <?php if ($key !== $priceField): ?>
                        <th class="num"><?= kipos_price_test_escape($key) ?></th>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($displayRows as $row): ?>
                <?php
                    $groupCode = kipos_price_test_department_code($row);
                    $rowPrice = kipos_price_test_row_price($row, $priceField);
                    $basePrice = $groupBasePrices[$groupCode] ?? 0.0;
                    $local = kipos_price_test_local_match($row, $products);
                    $product = $local['product'];
                    $variant = $local['variant'];
                    $productSame = $product && abs((float) $product->base_price - $basePrice) < 0.005;
                    $variantSame = $variant && abs((float) $variant->price_override - $rowPrice) < 0.005;
                ?>
                <tr>
                    <td><strong><?= kipos_price_test_escape(kipos_price_test_item_code($row)) ?></strong></td>
                    <td><?= kipos_price_test_escape($groupCode) ?></td>
                    <td><?= kipos_price_test_escape(kipos_price_test_string($row, 'NAZIV', 'NAZIV_ODJELA')) ?></td>
                    <td><?= kipos_price_test_escape(kipos_price_test_string($row, 'IDVELICINA')) ?></td>
                    <td class="num"><?= kipos_price_test_escape($row[$priceField] ?? '') ?></td>
                    <td class="num calculated"><?= kipos_price_test_format($rowPrice) ?></td>
                    <td class="num calculated"><?= kipos_price_test_format($basePrice) ?></td>
                    <td class="num <?= $productSame ? 'same' : 'different' ?>"><?= $product ? kipos_price_test_format($product->base_price) : '—' ?></td>
                    <td class="num <?= $variantSame ? 'same' : 'different' ?>"><?= $variant ? kipos_price_test_format($variant->price_override) : '—' ?></td>
                    <td><?= ! $product ? 'Nema proizvoda' : ($variant ? 'Proizvod + varijanta' : 'Samo proizvod') ?></td>
                    <?php foreach ($priceKeys as $key): ?>
                        <?php if ($key !== $priceField): ?>
                            <td class="num"><?= kipos_price_test_escape($row[$key] ?? '') ?></td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($displayRows === []): ?>
                <tr><td colspan="<?= 10 + count($priceKeys) ?>" class="muted">Nema rezultata.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
