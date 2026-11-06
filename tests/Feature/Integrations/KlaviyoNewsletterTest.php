<?php

namespace Tests\Feature\Integrations;

use App\Models\Settings\Local\Language;
use App\Services\Front\NewsletterSignupService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class KlaviyoNewsletterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['hr', 'de', 'en'] as $index => $code) {
            Language::query()->updateOrCreate(['code' => $code], [
                'locale' => $code,
                'name' => $code,
                'native_name' => $code,
                'direction' => 'ltr',
                'is_active' => true,
                'is_default' => $code === 'hr',
                'sort_order' => $index,
            ]);
        }

        app(SystemSettingsService::class)->putMany([
            'store_newsletter_provider' => 'klaviyo',
            'store_newsletter_klaviyo_api_key' => 'test-private-key',
            'store_newsletter_klaviyo_list_id' => 'default-list',
            'store_newsletter_klaviyo_list_id_de' => 'german-list',
            'store_email_enabled' => true,
        ]);

        Mail::fake();
        Http::preventStrayRequests();
        Http::fake([
            'https://a.klaviyo.com/api/profile-subscription-bulk-create-jobs/' => Http::response([], 202),
        ]);
    }

    public static function storefrontLanguages(): array
    {
        return [
            'German' => ['de', 'german-list'],
            'German in Austria' => ['de_AT', 'german-list'],
            'Croatian' => ['hr', 'default-list'],
            'English' => ['en', 'default-list'],
        ];
    }

    #[DataProvider('storefrontLanguages')]
    public function test_footer_subscription_uses_the_list_for_the_active_language(string $locale, string $listId): void
    {
        $this->postJson('/newsletter/subscribe?lang='.$locale, [
            'newsletter_email' => 'newsletter@example.test',
            'newsletter_accept_terms' => '1',
        ])->assertOk()->assertJson(['type' => 'success']);

        $this->assertSentToList($listId);
    }

    public function test_footer_subscription_uses_the_previously_selected_german_language(): void
    {
        $this->withSession(['front_locale' => 'de'])
            ->postJson('/newsletter/subscribe', [
                'newsletter_email' => 'newsletter@example.test',
                'newsletter_accept_terms' => '1',
            ])->assertOk()->assertJson(['type' => 'success']);

        $this->assertSentToList('german-list');
    }

    public function test_german_subscription_does_not_fall_back_to_the_default_list_when_unconfigured(): void
    {
        app(SystemSettingsService::class)->put('store_newsletter_klaviyo_list_id_de', '');

        $this->postJson('/newsletter/subscribe?lang=de', [
            'newsletter_email' => 'newsletter@example.test',
            'newsletter_accept_terms' => '1',
        ])->assertUnprocessable()->assertJsonValidationErrors('newsletter_email');

        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_signup_service_passes_its_explicit_locale_instead_of_the_application_locale(): void
    {
        App::setLocale('hr');

        app(NewsletterSignupService::class)->subscribe(
            email: ' Newsletter@Example.Test ',
            consentAccepted: true,
            locale: ' DE-at ',
        );

        $this->assertSentToList('german-list');
    }

    public function test_footer_rejects_a_german_subscription_without_explicit_consent(): void
    {
        $this->postJson('/newsletter/subscribe?lang=de', [
            'newsletter_email' => 'newsletter@example.test',
        ])->assertUnprocessable()->assertJsonValidationErrors('newsletter_accept_terms');

        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_command_configures_the_supplied_id_idempotently_without_changing_the_default_list(): void
    {
        $settings = app(SystemSettingsService::class);
        $settings->put('store_newsletter_klaviyo_list_id_de', '');

        $this->artisan('klaviyo:configure-german-newsletter')->assertSuccessful();
        $this->artisan('klaviyo:configure-german-newsletter')->assertSuccessful();

        $this->assertSame('V3EEiA', $settings->get('store_newsletter_klaviyo_list_id_de'));
        $this->assertSame('default-list', $settings->get('store_newsletter_klaviyo_list_id'));
        Http::assertNothingSent();
    }

    public function test_command_preserves_an_explicitly_configured_german_list(): void
    {
        $this->artisan('klaviyo:configure-german-newsletter')
            ->expectsOutputToContain('Preserved the existing German list ID')
            ->assertSuccessful();

        $this->assertSame('german-list', app(SystemSettingsService::class)->get('store_newsletter_klaviyo_list_id_de'));

        $this->artisan('klaviyo:configure-german-newsletter --force')->assertSuccessful();

        $this->assertSame('V3EEiA', app(SystemSettingsService::class)->get('store_newsletter_klaviyo_list_id_de'));
        Http::assertNothingSent();
    }

    private function assertSentToList(string $listId): void
    {
        Http::assertSent(static fn ($request): bool => $request->url() === 'https://a.klaviyo.com/api/profile-subscription-bulk-create-jobs/'
            && $request->hasHeader('Authorization', 'Klaviyo-API-Key test-private-key')
            && $request->hasHeader('revision', '2026-10-15')
            && $request['data']['attributes']['profiles']['data'][0]['attributes']['email'] === 'newsletter@example.test'
            && $request['data']['attributes']['profiles']['data'][0]['attributes']['subscriptions'] === [
                'email' => ['marketing' => ['consent' => 'SUBSCRIBED']],
            ]
            && ! ($request['data']['attributes']['historical_import'] ?? false)
            && $request['data']['relationships']['list']['data']['id'] === $listId);
        Http::assertSentCount(1);
        Mail::assertNothingSent();
    }
}
