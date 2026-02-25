<?php

namespace App\Services\Integrations\Luceed;

use Agmedia\Luceed\Auth\BasicAuth;
use Agmedia\Luceed\Auth\BearerTokenAuth;
use Agmedia\Luceed\Auth\HeaderAuth;
use Agmedia\Luceed\Auth\NoAuth;
use Agmedia\Luceed\Auth\QueryKeyAuth;
use Agmedia\Luceed\Contracts\AuthenticatorInterface;
use Agmedia\Luceed\LuceedClient;
use Agmedia\Luceed\LuceedConfig;
use Agmedia\Luceed\LuceedFactory;
use App\Services\Catalog\CatalogFeatureService;
use App\Services\Settings\SystemSettingsService;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use InvalidArgumentException;
use RuntimeException;

class LuceedSdkService
{
    public const PROBE_SIFRARNICI = 'sifrarnici';
    public const PROBE_SKLADISTA = 'skladista';
    public const PROBE_VRSTE_PLACANJA = 'vrste_placanja';

    public function __construct(
        private readonly SystemSettingsService $settings,
        private readonly CatalogFeatureService $catalogFeatures
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'luceed_api_enabled' => false,
            'luceed_api_base_uri' => '',
            'luceed_api_auth_type' => 'basic',
            'luceed_api_username' => '',
            'luceed_api_password' => '',
            'luceed_api_bearer_token' => '',
            'luceed_api_header_name' => 'X-Api-Key',
            'luceed_api_header_value' => '',
            'luceed_api_query_key' => 'api_key',
            'luceed_api_query_value' => '',
            'luceed_api_timeout_seconds' => 20,
            'luceed_api_verify_tls' => true,
            'luceed_api_probe' => self::PROBE_SIFRARNICI,
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
     * @param array<string, mixed> $payload
     */
    public function saveSettings(array $payload): void
    {
        $this->settings->putMany($payload);
    }

    /**
     * @param array<string, mixed>|null $override
     */
    public function makeClient(?array $override = null): LuceedClient
    {
        $settings = array_merge($this->getSettings(), $override ?? []);

        $baseUri = trim((string) ($settings['luceed_api_base_uri'] ?? ''));
        if ($baseUri === '') {
            throw new InvalidArgumentException('Luceed base URI is required.');
        }

        $timeout = (int) ($settings['luceed_api_timeout_seconds'] ?? 20);
        $timeout = max(2, min(120, $timeout));
        $verifyTls = (bool) ($settings['luceed_api_verify_tls'] ?? true);

        $httpClient = new GuzzleClient([
            'timeout' => $timeout,
            'connect_timeout' => min($timeout, 15),
            'verify' => $verifyTls,
        ]);

        $httpFactory = new HttpFactory();

        return LuceedFactory::fromPsr(
            config: new LuceedConfig($baseUri),
            httpClient: $httpClient,
            requestFactory: $httpFactory,
            streamFactory: $httpFactory,
            authenticator: $this->resolveAuthenticator($settings),
            defaultHeaders: [
                'User-Agent' => 'AGShop-Luceed-Connector/1.0',
            ],
        );
    }

    /**
     * @param array<string, mixed>|null $override
     * @return array{probe:string,result_count:int,first_item:array<string,mixed>|null}
     */
    public function testConnection(string $probe, ?array $override = null): array
    {
        $client = $this->makeClient($override);

        $rows = match ($probe) {
            self::PROBE_SIFRARNICI => array_map(
                static fn ($item): array => $item->data,
                $client->sifrarnici()->listCodebooks()
            ),
            self::PROBE_SKLADISTA => array_map(
                static fn ($item): array => $item->data,
                $client->skladista()->list()
            ),
            self::PROBE_VRSTE_PLACANJA => array_map(
                static fn ($item): array => $item->data,
                $client->vrstePlacanja()->list()
            ),
            default => throw new InvalidArgumentException('Unsupported Luceed probe endpoint.'),
        };

        return [
            'probe' => $probe,
            'result_count' => count($rows),
            'first_item' => $rows[0] ?? null,
        ];
    }

    public function assertEnabled(): void
    {
        if (! $this->catalogFeatures->enabled('catalog_use_luceed_api')) {
            throw new RuntimeException('Luceed API module is disabled in Catalog Features.');
        }

        if (! $this->enabledInSettings()) {
            throw new RuntimeException('Luceed connector is disabled in Luceed API settings.');
        }
    }

    public function enabledInSettings(): bool
    {
        return (bool) $this->settings->get('luceed_api_enabled', false);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function resolveAuthenticator(array $settings): AuthenticatorInterface
    {
        $authType = (string) ($settings['luceed_api_auth_type'] ?? 'basic');

        return match ($authType) {
            'none' => new NoAuth(),
            'bearer' => new BearerTokenAuth(trim((string) ($settings['luceed_api_bearer_token'] ?? ''))),
            'header' => new HeaderAuth(
                trim((string) ($settings['luceed_api_header_name'] ?? 'X-Api-Key')),
                trim((string) ($settings['luceed_api_header_value'] ?? ''))
            ),
            'query' => new QueryKeyAuth(
                trim((string) ($settings['luceed_api_query_key'] ?? 'api_key')),
                trim((string) ($settings['luceed_api_query_value'] ?? ''))
            ),
            default => new BasicAuth(
                trim((string) ($settings['luceed_api_username'] ?? '')),
                trim((string) ($settings['luceed_api_password'] ?? ''))
            ),
        };
    }
}
