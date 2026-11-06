<?php

namespace Tests\Feature\Front;

use App\Livewire\Admin\Settings\System\StoreSettings;
use App\Models\Settings\Local\Language;
use App\Models\User;
use App\Services\Front\StoreSettingsService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

class LocalizedAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Language::query()->update(['is_default' => false]);
        foreach (['hr', 'de', 'en'] as $index => $locale) {
            Language::query()->updateOrCreate(['code' => $locale], [
                'locale' => $locale,
                'name' => $locale,
                'native_name' => $locale,
                'direction' => 'ltr',
                'is_default' => $locale === 'hr',
                'is_active' => true,
                'sort_order' => $index,
            ]);
        }

        app(SystemSettingsService::class)->putMany([
            'store_announcement_text' => 'Hrvatska ponuda',
            'store_announcement_url' => 'https://example.com/hr',
        ]);
    }

    public function test_admin_can_switch_and_save_announcement_languages_without_overwriting_croatian(): void
    {
        $admin = User::factory()->create();
        Bouncer::role()->firstOrCreate(['name' => 'admin']);
        Bouncer::assign('admin')->to($admin);

        Livewire::actingAs($admin)->test(StoreSettings::class)
            ->set('tab', 'announcement')
            ->set('locale', 'hr')
            ->assertSet('form.store_announcement_text', 'Hrvatska ponuda')
            ->set('locale', 'de')
            ->assertSet('form.store_announcement_text', '')
            ->set('form.store_announcement_text', 'Angebote für Deutschland')
            ->set('form.store_announcement_url', 'https://example.com/de')
            ->set('locale', 'hr')
            ->assertSet('form.store_announcement_text', 'Hrvatska ponuda')
            ->set('locale', 'de')
            ->assertSet('form.store_announcement_text', 'Angebote für Deutschland')
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(SystemSettingsService::class);
        $this->assertSame('Hrvatska ponuda', $settings->get('store_announcement_text'));
        $this->assertSame('https://example.com/hr', $settings->get('store_announcement_url'));
        $this->assertSame('Angebote für Deutschland', $settings->get('store_announcement_text_translations')['de']);

        Livewire::actingAs($admin)->test(StoreSettings::class)
            ->set('tab', 'announcement')
            ->set('locale', 'de')
            ->assertSet('form.store_announcement_text', 'Angebote für Deutschland')
            ->assertSet('form.store_announcement_url', 'https://example.com/de');
    }

    public function test_german_announcement_uses_localized_defaults_without_inheriting_croatian_campaign_or_link(): void
    {
        app()->setLocale('de');
        $announcement = app(StoreSettingsService::class)->announcement();
        $this->assertSame('Günstige Preise – besondere Angebote', $announcement['text']);
        $this->assertSame('', $announcement['url']);

        app()->setLocale('hr');
        $announcement = app(StoreSettingsService::class)->announcement();
        $this->assertSame('Hrvatska ponuda', $announcement['text']);
        $this->assertSame('https://example.com/hr', $announcement['url']);
    }

    public function test_opening_german_settings_directly_does_not_prefill_admin_language_as_german_copy(): void
    {
        $admin = User::factory()->create();
        Bouncer::role()->firstOrCreate(['name' => 'admin']);
        Bouncer::assign('admin')->to($admin);
        app()->setLocale('hr');

        Livewire::withQueryParams(['locale' => 'de'])
            ->actingAs($admin)->test(StoreSettings::class)
            ->assertSet('locale', 'de')
            ->assertSet('form.store_announcement_text', '')
            ->assertSet('form.store_announcement_url', '');
    }

    public function test_both_device_templates_render_the_current_language_and_keep_bar_controls(): void
    {
        $settings = app(SystemSettingsService::class);
        $settings->putMany([
            'store_announcement_text_translations' => ['de' => 'Angebote für Deutschland'],
            'store_announcement_url_translations' => ['de' => 'https://example.com/de'],
            'store_announcement_scroll_enabled' => true,
            'store_announcement_scroll_duration_seconds' => 24,
            'store_announcement_background_color' => '#123456',
        ]);

        foreach ([false, true] as $mobile) {
            $settings->put('catalog_use_mobile_view', $mobile);
            $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1'])
                ->withSession(['front_locale' => 'de'])
                ->get('/')
                ->assertOk()
                ->assertSee('Angebote für Deutschland')
                ->assertSee('href="https://example.com/de"', false)
                ->assertDontSee('Hrvatska ponuda')
                ->assertSee('--store-announcement-duration: 24s', false)
                ->assertSee('background-color: #123456', false)
                ->assertSee('prefers-reduced-motion', false);
        }
    }
}
