<?php

namespace Tests\Feature\Front;

use App\Models\Content\Page\InfoPage;
use App\Models\Content\Page\InfoPageTranslation;
use App\Models\Settings\Local\Language;
use App\Services\Front\LegalPageLinkService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LegalPageFunctionalLinksTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('frontendVariants')]
    public function test_german_legal_markers_become_guest_functions_without_rewriting_the_saved_text(bool $mobile): void
    {
        foreach (['hr', 'de'] as $locale) {
            Language::query()->updateOrCreate(['code' => $locale], [
                'locale' => $locale, 'name' => $locale, 'native_name' => $locale,
                'direction' => 'ltr', 'is_active' => true, 'is_default' => $locale === 'hr',
            ]);
        }
        app(SystemSettingsService::class)->put('catalog_use_mobile_view', $mobile);
        $page = InfoPage::query()->create(['code' => 'legal', 'is_active' => true]);
        $body = '<p>Unveränderter Rechtstext <strong>[FUNKTIONSLINK - Vertrag widerrufen]</strong>.</p>'
            .'<p>Rücksendungen: <strong>[FUNKTIONSLINK – Retourenportal]</strong>.</p>'
            .'<p>Privatsphäre: <strong>[FUNKTIONSLINK - Cookie-Einstellungen]</strong>.</p>';
        $translation = InfoPageTranslation::query()->create([
            'page_id' => $page->id, 'locale' => 'de', 'title' => 'Impressum und Nutzungsbedingungen',
            'slug' => 'nutzungsbedingungen', 'body_html' => $body,
        ]);
        $response = $this->withHeaders(['User-Agent' => $mobile ? 'Mozilla/5.0 iPhone Mobile' : 'Mozilla/5.0 Macintosh'])
            ->withSession(['front_locale' => 'de'])
            ->get('/page/nutzungsbedingungen?lang=de');

        $portal = route('returns.create', ['returnRequestSlug' => 'rucksendungen-und-reklamationen', 'lang' => 'de']);
        $withdrawal = route('returns.create', ['returnRequestSlug' => 'rucksendungen-und-reklamationen', 'lang' => 'de', 'withdrawal' => 1]);
        $response->assertOk()
            ->assertSee('Unveränderter Rechtstext')
            ->assertDontSee('FUNKTIONSLINK')
            ->assertSee('href="'.e($portal).'"', false)
            ->assertSee('href="'.e($withdrawal).'"', false)
            ->assertSee('type="button" data-cookie-consent-preferences', false)
            ->assertSee('openCookiePreferences', false);
        $this->assertDatabaseHas('content_info_page_translations', ['id' => $translation->id, 'body_html' => $body]);

        $this->get($portal)->assertOk()->assertSee('Formular für Rücksendungen und Reklamationen');
        $this->get($withdrawal)->assertOk()->assertSee('Widerruf');
        $this->assertGuest();
    }

    public function test_other_locales_and_unrecognized_markers_are_left_unchanged(): void
    {
        $html = '<p>[FUNKTIONSLINK - Retourenportal] [FUNKTIONSLINK - Unbekannt]</p>';
        $service = app(LegalPageLinkService::class);
        $this->assertSame($html, $service->render($html, 'hr'));
        $this->assertSame($html, $service->render($html, 'en'));
        $this->assertStringContainsString('[FUNKTIONSLINK - Unbekannt]', $service->render($html, 'de'));
    }

    public static function frontendVariants(): array
    {
        return ['desktop' => [false], 'mobile' => [true]];
    }
}
