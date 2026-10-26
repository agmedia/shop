<?php

namespace Tests\Feature\Front;

use App\Models\Catalog\Category\Category;
use App\Models\Catalog\Category\CategoryTranslation;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cookie;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StorefrontPrivacyPreferencesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.locale' => 'en', 'app.fallback_locale' => 'en']);
        app()->setLocale('en');
        $category = Category::query()->create([
            'scope' => Category::SCOPE_CATALOG, 'code' => 'privacy-category', 'is_active' => true,
        ]);
        CategoryTranslation::query()->create([
            'category_id' => $category->id, 'scope' => Category::SCOPE_CATALOG,
            'locale' => 'en', 'name' => 'Privacy category', 'slug' => 'privacy-category',
        ]);
    }

    #[DataProvider('productLists')]
    public function test_default_listing_does_not_create_a_layout_cookie(string $url): void
    {
        $this->get($url)->assertOk()->assertCookieMissing('front_grid_cols');
    }

    #[DataProvider('productLists')]
    public function test_existing_layout_is_read_without_renewing_its_expiry(string $url): void
    {
        $this->withCookie('front_grid_cols', '5')->get($url)
            ->assertOk()->assertViewHas($url === '/wishlist' ? 'cols' : 'filters.cols', 5)->assertCookieMissing('front_grid_cols');
    }

    #[DataProvider('productLists')]
    public function test_explicit_layout_choice_is_remembered_for_365_days(string $url): void
    {
        $this->travelTo(now()->startOfSecond());
        $response = $this->get($url.'?cols=5')->assertCookie('front_grid_cols', '5');
        $this->assertSame(now()->addDays(365)->getTimestamp(), $response->getCookie('front_grid_cols')->getExpiresTime());
    }

    #[DataProvider('productLists')]
    public function test_empty_and_invalid_layout_values_do_not_create_cookies(string $url): void
    {
        foreach (['', '0', '6', '4junk', '4&cols[]=5'] as $value) {
            Cookie::flushQueuedCookies();
            $this->get($url.'?cols='.$value)->assertCookieMissing('front_grid_cols');
        }
    }

    #[DataProvider('protectedForms')]
    public function test_form_pages_define_a_lazy_loader_without_fetching_google_on_page_load(string $url, string $agent): void
    {
        app(SystemSettingsService::class)->putMany([
            'catalog_use_mobile_view' => true,
            'store_captcha_recaptcha_v3_enabled' => true,
            'store_captcha_recaptcha_v3_site_key' => 'privacy-test-key',
            'store_captcha_recaptcha_v3_secret_key' => 'privacy-test-secret',
        ]);
        $response = $this->withHeader('User-Agent', $agent)->get($url)
            ->assertOk()->assertSee('data-recaptcha-site-key="privacy-test-key"', false)
            ->assertSee('window.getStoreRecaptchaToken', false);
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        foreach ($document->getElementsByTagName('script') as $script) {
            $this->assertStringNotContainsString('google.com/recaptcha/', $script->getAttribute('src'));
        }
        if ($url === '/contact') {
            $xpath = new \DOMXPath($document);
            foreach (['name', 'email', 'phone', 'subject', 'message', 'accept_terms'] as $field) {
                $control = $xpath->query('//*[@data-contact-form]//*[@name="'.$field.'"]')->item(0);
                $this->assertNotNull($control);
                $id = $control->getAttribute('id');
                $this->assertNotSame('', $id);
                $this->assertCount(1, $xpath->query('//label[@for="'.$id.'"]'));
            }
        }
    }

    public static function productLists(): array
    {
        return [['/shop'], ['/category/privacy-category'], ['/wishlist']];
    }

    public static function protectedForms(): array
    {
        $cases = [];
        foreach (['desktop' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X)', 'mobile' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)'] as $device => $agent) {
            foreach (['/contact', '/forma-za-povrat-i-reklamacije', '/auth/login', '/auth/register'] as $url) {
                $cases[$device.$url] = [$url, $agent];
            }
        }

        return $cases;
    }
}
