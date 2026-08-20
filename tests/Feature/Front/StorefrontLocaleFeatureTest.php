<?php

namespace Tests\Feature\Front;

use App\Models\Catalog\Category\Category;
use App\Models\Catalog\Category\CategoryTranslation;
use App\Models\Settings\Local\Language;
use App\Models\Settings\Local\PaymentMethod;
use App\Models\Settings\Local\ShippingMethod;
use App\Services\Front\CheckoutService;
use App\Services\Front\StoreSettingsService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class StorefrontLocaleFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_front_locale_switch_persists_selected_language_between_requests(): void
    {
        $this->seedLanguages();

        $this->get('/contact')
            ->assertOk()
            ->assertSee('lang="hr"', false);

        $this->from('/contact')
            ->get('/locale/en')
            ->assertRedirect('/contact')
            ->assertSessionHas('front_locale', 'en');

        $this->get('/contact')
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSessionHas('front_locale', 'en');
    }

    public function test_return_request_form_has_localized_slugs(): void
    {
        $this->seedLanguages();

        $this->get('/forma-za-povrat-i-reklamacije')
            ->assertOk()
            ->assertSee('Forma za povrat i reklamacije');

        $this->from('/contact')->get('/locale/en');

        $this->get('/returns-and-claims')
            ->assertOk()
            ->assertSee('Returns and claims form')
            ->assertSee('/returns-and-claims', false);

        $this->from('/returns-and-claims')->get('/locale/de');

        $this->get('/rucksendungen-und-reklamationen')
            ->assertOk()
            ->assertSee('Formular für Rücksendungen und Reklamationen')
            ->assertSee('/rucksendungen-und-reklamationen', false);
    }

    public function test_core_storefront_pages_render_in_every_configured_language(): void
    {
        $this->seedLanguages();

        foreach (['hr', 'en', 'de'] as $locale) {
            foreach (['/', '/shop', '/categories', '/faq', '/contact', '/cart'] as $path) {
                $response = $this->withSession(['front_locale' => $locale])->get($path);

                $this->assertSame(200, $response->status(), $locale.' storefront route failed: '.$path);
                $response->assertSee('lang="'.$locale.'"', false);
            }
        }
    }

    public function test_language_switcher_exposes_english_and_german(): void
    {
        $this->seedLanguages();

        $this->withSession(['front_locale' => 'hr'])
            ->get('/?frontend_variant=desktop')
            ->assertOk()
            ->assertSee('/locale/en', false)
            ->assertSee('/locale/de', false);
    }

    public function test_client_supplied_german_copy_and_checkout_method_names_are_used(): void
    {
        $this->seedLanguages();
        App::setLocale('de');

        ShippingMethod::query()->create([
            'code' => 'standard',
            'name' => 'Dostava DPD Hrvatska',
            'price' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        PaymentMethod::query()->create([
            'code' => 'corvuspay',
            'name' => 'Plaćanje kreditnim i debitnim karticama - Corvuspay',
            'fee_type' => 'fixed',
            'fee_value' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $checkout = app(CheckoutService::class);

        $this->assertSame('Mein Konto', __('ui.front.desktop.account'));
        $this->assertSame('Materialzusammensetzung', __('ui.product.attribute_groups.composition'));
        $this->assertSame(
            'Versand mit DPD Kroatien – Kostenloser Versand für Bestellungen über 50 €',
            $checkout->availableShippingMethods(60)->first()?->display_name
        );
        $this->assertSame(
            'Zahlung mit Kredit- und Debitkarten – CorvusPay',
            $checkout->availablePaymentMethods(60)->first()?->display_name
        );
    }

    public function test_german_footer_uses_lang_copy_when_database_has_no_german_override(): void
    {
        app(SystemSettingsService::class)->putMany([
            'store_footer_hours' => 'PON - PET od 8 do 16h',
            'store_footer_col_2_title' => 'Pomoć',
            'store_footer_col_2_title_translations' => [
                'hr' => 'Pomoć',
                'en' => 'Help',
            ],
        ]);

        config(['app.locale' => 'hr', 'app.fallback_locale' => 'hr']);
        App::setLocale('de');

        $footer = app(StoreSettingsService::class)->footer();

        $this->assertSame('Mo. – Fr. von 8:00 bis 16:00 Uhr', $footer['hours']);
        $this->assertSame('HILFE', $footer['link_columns'][1]['title']);
    }

    public function test_category_language_switch_redirects_to_the_localized_slug(): void
    {
        $this->seedLanguages();

        $category = Category::query()->create([
            'scope' => Category::SCOPE_CATALOG,
            'code' => 'men',
            'is_active' => true,
            'show_in_menu' => true,
            'sort_order' => 1,
        ]);

        CategoryTranslation::query()->create([
            'category_id' => $category->id,
            'scope' => Category::SCOPE_CATALOG,
            'locale' => 'hr',
            'name' => 'Muškarci',
            'slug' => 'muskarci',
            'description' => null,
        ]);

        CategoryTranslation::query()->create([
            'category_id' => $category->id,
            'scope' => Category::SCOPE_CATALOG,
            'locale' => 'en',
            'name' => 'Men',
            'slug' => 'men',
            'description' => null,
        ]);

        CategoryTranslation::query()->create([
            'category_id' => $category->id,
            'scope' => Category::SCOPE_CATALOG,
            'locale' => 'de',
            'name' => 'Herren',
            'slug' => 'herren',
            'description' => null,
        ]);

        $languages = [
            'hr' => ['slug' => 'muskarci', 'name' => 'Muškarci'],
            'en' => ['slug' => 'men', 'name' => 'Men'],
            'de' => ['slug' => 'herren', 'name' => 'Herren'],
        ];

        foreach ($languages as $sourceLocale => $source) {
            $sourcePath = '/category/'.$source['slug'];

            $this->withSession(['front_locale' => $sourceLocale])
                ->get($sourcePath)
                ->assertOk()
                ->assertSee($source['name'])
                ->assertSee('lang="'.$sourceLocale.'"', false);

            foreach ($languages as $targetLocale => $target) {
                if ($targetLocale === $sourceLocale) {
                    continue;
                }

                $targetPath = '/category/'.$target['slug'];

                $this->withSession(['front_locale' => $sourceLocale])
                    ->from($sourcePath)
                    ->get('/locale/'.$targetLocale)
                    ->assertRedirect($sourcePath)
                    ->assertSessionHas('front_locale', $targetLocale);

                $this->get($sourcePath.'?sort=newest')
                    ->assertRedirect($targetPath.'?sort=newest');

                $this->get($targetPath)
                    ->assertOk()
                    ->assertSee($target['name'])
                    ->assertSee('lang="'.$targetLocale.'"', false)
                    ->assertSessionHas('front_locale', $targetLocale);
            }
        }
    }

    private function seedLanguages(): void
    {
        Language::query()->create([
            'code' => 'hr',
            'locale' => 'hr_HR',
            'name' => 'Croatian',
            'native_name' => 'Hrvatski',
            'direction' => 'ltr',
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Language::query()->create([
            'code' => 'en',
            'locale' => 'en_US',
            'name' => 'English',
            'native_name' => 'English',
            'direction' => 'ltr',
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        Language::query()->updateOrCreate([
            'code' => 'de',
        ], [
            'locale' => 'de_DE',
            'name' => 'German',
            'native_name' => 'Deutsch',
            'direction' => 'ltr',
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 3,
        ]);
    }
}
