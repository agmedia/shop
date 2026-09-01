<?php

namespace App\Services\Integrations\Kipos;

use App\Services\Catalog\CatalogFeatureService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use InvalidArgumentException;
use RuntimeException;

class KiposSdkService
{
    public const PROBE_ITEMS = 'sif_roba/getitems';

    private const GET_RETRY_ATTEMPTS = 3;

    private const GET_RETRY_DELAY_MICROSECONDS = 250000;

    public function __construct(
        private readonly SystemSettingsService $settings,
        private readonly CatalogFeatureService $catalogFeatures,
        private readonly HttpFactory $http
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'kipos_api_enabled' => false,
            'kipos_api_base_uri' => 'http://balidd.dyndns.org:8080/kipos.web.api/?route=',
            'kipos_api_image_base_uri' => 'http://balidd.dyndns.org:8080/slike/',
            'kipos_api_query_suffix' => 'webshop=1',
            'kipos_api_timeout_seconds' => 30,
            'kipos_api_verify_tls' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        $settings = $this->defaults();

        foreach ($settings as $key => $defaultValue) {
            $settings[$key] = $this->settings->get($key, $defaultValue);
        }

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function saveSettings(array $payload): void
    {
        $this->settings->putMany($payload);
    }

    /**
     * @param  array<string, mixed>|null  $override
     * @return array{probe:string,result_count:int,first_item:array<string,mixed>|null}
     */
    public function testConnection(?array $override = null): array
    {
        $rows = $this->getRows(self::PROBE_ITEMS, [], $override);

        return [
            'probe' => self::PROBE_ITEMS,
            'result_count' => count($rows),
            'first_item' => $rows[0] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $override
     * @return list<array<string, mixed>>
     */
    public function getRows(string $route, array $query = [], ?array $override = null): array
    {
        return $this->normalizeRows($this->get($route, $query, $override));
    }

    /**
     * @param  array<string, mixed>|null  $override
     * @return array<string, mixed>|list<mixed>
     */
    public function get(string $route, array $query = [], ?array $override = null): array
    {
        return $this->request('get', $route, [], $query, $override);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $override
     * @return array<string, mixed>|list<mixed>
     */
    public function post(string $route, array $payload, array $query = [], ?array $override = null): array
    {
        return $this->request('post', $route, $payload, $query, $override);
    }

    public function resolveImageUrl(?string $rawUrl, ?array $settings = null): ?string
    {
        $rawUrl = trim((string) $rawUrl);
        if ($rawUrl === '') {
            return null;
        }

        if (str_starts_with($rawUrl, 'http://') || str_starts_with($rawUrl, 'https://')) {
            return $rawUrl;
        }

        $settings = $settings ?? $this->getSettings();
        $base = rtrim(trim((string) ($settings['kipos_api_image_base_uri'] ?? '')), '/');
        if ($base === '') {
            return null;
        }

        return $base.'/'.ltrim($rawUrl, '/');
    }

    public function assertEnabled(): void
    {
        if (! $this->catalogFeatures->useKiposApi()) {
            throw new RuntimeException('Kipos API module is disabled in Catalog Features.');
        }

        if (! $this->enabledInSettings()) {
            throw new RuntimeException('Kipos connector is disabled in Kipos API settings.');
        }
    }

    public function enabledInSettings(): bool
    {
        return (bool) $this->settings->get('kipos_api_enabled', false);
    }

    /**
     * @param  array<string, mixed>|null  $override
     * @return array<string, mixed>|list<mixed>
     */
    private function request(string $method, string $route, array $payload = [], array $query = [], ?array $override = null): array
    {
        $settings = array_merge($this->getSettings(), $override ?? []);
        $url = $this->buildUrl($route, $query, $settings);
        $timeout = max(5, min(120, (int) ($settings['kipos_api_timeout_seconds'] ?? 30)));

        $client = $this->http
            ->acceptJson()
            ->asJson()
            ->connectTimeout(min($timeout, 15))
            ->timeout($timeout)
            ->withOptions(['decode_content' => true])
            ->withHeaders([
                'Accept-Encoding' => 'gzip',
                'User-Agent' => 'AGShop-Kipos-Connector/1.0',
            ]);

        if (! (bool) ($settings['kipos_api_verify_tls'] ?? true)) {
            $client = $client->withoutVerifying();
        }

        $response = $method === 'post'
            ? $client->post($url, $payload)
            : $this->performGetWithRetry($client, $url);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Kipos request failed (%d): %s',
                $response->status(),
                trim($response->body()) !== '' ? trim($response->body()) : 'empty response body'
            ));
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            throw new RuntimeException('Kipos response is not valid JSON array/object.');
        }

        return $decoded;
    }

    private function performGetWithRetry(PendingRequest $client, string $url)
    {
        for ($attempt = 1; $attempt <= self::GET_RETRY_ATTEMPTS; $attempt++) {
            try {
                return $client->get($url);
            } catch (ConnectionException $exception) {
                if ($attempt === self::GET_RETRY_ATTEMPTS) {
                    throw $exception;
                }

                usleep(self::GET_RETRY_DELAY_MICROSECONDS);
            }
        }

        throw new RuntimeException('Kipos GET retry loop finished without a response.');
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function buildUrl(string $route, array $query, array $settings): string
    {
        $baseUri = trim((string) ($settings['kipos_api_base_uri'] ?? ''));
        if ($baseUri === '') {
            throw new InvalidArgumentException('Kipos base URI is required.');
        }

        if (! str_contains(strtolower($baseUri), '?route=')) {
            $baseUri = rtrim($baseUri, '/').'/?route=';
        }

        $route = ltrim(trim($route), '/');
        if ($route === '') {
            throw new InvalidArgumentException('Kipos route is required.');
        }

        $suffixQuery = [];
        parse_str(ltrim(trim((string) ($settings['kipos_api_query_suffix'] ?? '')), '?&'), $suffixQuery);

        $url = $baseUri.$route;
        $params = array_merge($suffixQuery, $query);

        if ($params !== []) {
            $url .= '&'.http_build_query($params);
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function normalizeRows(array $payload): array
    {
        if (array_is_list($payload)) {
            return array_values(array_filter(
                $payload,
                static fn ($row): bool => is_array($row)
            ));
        }

        foreach (['items', 'rows', 'data', 'result'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                /** @var array<string, mixed>|list<mixed> $nested */
                $nested = $payload[$key];

                return $this->normalizeRows($nested);
            }
        }

        return [$payload];
    }
}
