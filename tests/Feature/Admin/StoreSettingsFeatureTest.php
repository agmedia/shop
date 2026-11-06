<?php

namespace Tests\Feature\Admin;

use App\Jobs\GenerateWebpConversionsJob;
use App\Livewire\Admin\Settings\System\StoreSettings;
use App\Models\Catalog\Product\Product;
use App\Models\Settings\Local\Language;
use App\Models\User;
use App\Services\Front\StoreSettingsService as FrontStoreSettingsService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class StoreSettingsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_store_settings_page(): void
    {
        $admin = $this->makeUserWithRole('admin');

        $this->actingAs($admin)
            ->get('/admin/settings/system/store-settings')
            ->assertOk()
            ->assertSee(__('Store Settings'))
            ->assertSee(route('admin.settings.system.store-settings'));
    }

    public function test_editor_cannot_open_store_settings_page(): void
    {
        $editor = $this->makeUserWithRole('editor');

        $this->actingAs($editor)
            ->get('/admin/settings/system/store-settings')
            ->assertForbidden();
    }

    public function test_products_tab_can_save_even_when_newsletter_tab_is_invalid(): void
    {
        $admin = $this->makeUserWithRole('admin');

        app(SystemSettingsService::class)->putMany([
            'store_newsletter_provider' => 'mailchimp',
            'store_newsletter_mailchimp_api_key' => '',
            'store_newsletter_mailchimp_list_id' => '',
            'store_product_fit_finder_enabled' => false,
            'store_search_autocomplete_enabled' => false,
            'store_product_desktop_default_cols' => 4,
            'store_product_mobile_default_cols' => 1,
            'store_product_catalog_pagination_mode' => 'pagination',
            'store_product_filter_option_ids' => [],
            'store_product_filter_attribute_group_codes' => [],
        ]);

        Livewire::actingAs($admin)
            ->test(StoreSettings::class)
            ->set('tab', 'products')
            ->set('form.store_product_fit_finder_enabled', true)
            ->set('form.store_search_autocomplete_enabled', true)
            ->set('form.store_product_desktop_default_cols', 5)
            ->set('form.store_product_mobile_default_cols', 2)
            ->set('form.store_product_catalog_pagination_mode', 'load_more')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $settings = app(SystemSettingsService::class);

        $this->assertTrue((bool) $settings->get('store_product_fit_finder_enabled'));
        $this->assertTrue((bool) $settings->get('store_search_autocomplete_enabled'));
        $this->assertSame(5, (int) $settings->get('store_product_desktop_default_cols'));
        $this->assertSame(2, (int) $settings->get('store_product_mobile_default_cols'));
        $this->assertSame('load_more', $settings->get('store_product_catalog_pagination_mode'));
        $this->assertSame('mailchimp', $settings->get('store_newsletter_provider'));
        $this->assertSame('', $settings->get('store_newsletter_mailchimp_api_key'));
        $this->assertSame('', $settings->get('store_newsletter_mailchimp_list_id'));
    }

    public function test_newsletter_tab_still_requires_mailchimp_credentials_when_active(): void
    {
        $admin = $this->makeUserWithRole('superadmin');

        Livewire::actingAs($admin)
            ->test(StoreSettings::class)
            ->set('tab', 'newsletter')
            ->set('form.store_newsletter_provider', 'mailchimp')
            ->set('form.store_newsletter_mailchimp_api_key', '')
            ->set('form.store_newsletter_mailchimp_list_id', '')
            ->call('save')
            ->assertHasErrors([
                'form.store_newsletter_mailchimp_api_key',
                'form.store_newsletter_mailchimp_list_id',
            ]);
    }

    public function test_newsletter_tab_saves_and_reloads_the_german_klaviyo_list_separately(): void
    {
        $admin = $this->makeUserWithRole('admin');

        Livewire::actingAs($admin)
            ->test(StoreSettings::class)
            ->set('tab', 'newsletter')
            ->assertSee('form.store_newsletter_klaviyo_list_id_de', false)
            ->set('form.store_newsletter_provider', 'klaviyo')
            ->set('form.store_newsletter_klaviyo_api_key', 'test-private-key')
            ->set('form.store_newsletter_klaviyo_list_id', 'default-list')
            ->set('form.store_newsletter_klaviyo_list_id_de', 'V3EEiA')
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(SystemSettingsService::class);
        $this->assertSame('default-list', $settings->get('store_newsletter_klaviyo_list_id'));
        $this->assertSame('V3EEiA', $settings->get('store_newsletter_klaviyo_list_id_de'));
        $this->assertSame('V3EEiA', app(FrontStoreSettingsService::class)->newsletter()['klaviyo_list_id_de']);

        Livewire::actingAs($admin)
            ->test(StoreSettings::class)
            ->assertSet('form.store_newsletter_klaviyo_list_id_de', 'V3EEiA');
    }

    public function test_german_klaviyo_list_can_remain_empty_for_other_storefront_languages(): void
    {
        $admin = $this->makeUserWithRole('admin');

        Livewire::actingAs($admin)
            ->test(StoreSettings::class)
            ->set('tab', 'newsletter')
            ->set('form.store_newsletter_provider', 'klaviyo')
            ->set('form.store_newsletter_klaviyo_api_key', 'test-private-key')
            ->set('form.store_newsletter_klaviyo_list_id', 'default-list')
            ->set('form.store_newsletter_klaviyo_list_id_de', '')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_german_klaviyo_list_rejects_overlong_values(): void
    {
        $admin = $this->makeUserWithRole('admin');

        Livewire::actingAs($admin)
            ->test(StoreSettings::class)
            ->set('tab', 'newsletter')
            ->set('form.store_newsletter_klaviyo_list_id_de', str_repeat('a', 256))
            ->call('save')
            ->assertHasErrors(['form.store_newsletter_klaviyo_list_id_de' => 'max']);
    }

    public function test_announcement_tab_saves_scroll_and_color_settings(): void
    {
        $admin = $this->makeUserWithRole('admin');

        Livewire::actingAs($admin)
            ->test(StoreSettings::class)
            ->set('tab', 'announcement')
            ->set('form.store_announcement_enabled', true)
            ->set('form.store_announcement_text', 'Promo')
            ->set('form.store_announcement_url', 'https://example.com/promo')
            ->set('form.store_announcement_new_tab', true)
            ->set('form.store_announcement_scroll_enabled', true)
            ->set('form.store_announcement_scroll_duration_seconds', 24)
            ->set('form.store_announcement_background_color', '#0ea5e9')
            ->set('form.store_announcement_text_color', '#ffffff')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $settings = app(SystemSettingsService::class);

        $this->assertTrue((bool) $settings->get('store_announcement_scroll_enabled'));
        $this->assertSame(24, (int) $settings->get('store_announcement_scroll_duration_seconds'));
        $this->assertSame('#0ea5e9', $settings->get('store_announcement_background_color'));
        $this->assertSame('#ffffff', $settings->get('store_announcement_text_color'));
    }

    public function test_footer_text_and_custom_links_are_saved_per_locale(): void
    {
        $admin = $this->makeUserWithRole('admin');
        config(['app.locale' => 'hr']);

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
            'is_active' => false,
            'sort_order' => 2,
        ]);

        app(SystemSettingsService::class)->putMany([
            'store_footer_col_2_title' => 'Pomoć',
            'store_footer_col_2_custom_links' => 'Kontakt|/contact',
        ]);

        Livewire::actingAs($admin)
            ->test(StoreSettings::class)
            ->set('tab', 'branding')
            ->set('locale', 'en')
            ->set('form.store_footer_col_2_title', 'Help')
            ->set('form.store_footer_col_2_custom_links', "Contact|/contact\nReturns and claims form|/returns-and-claims")
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(SystemSettingsService::class);
        $this->assertSame('Pomoć', $settings->get('store_footer_col_2_title'));
        $this->assertSame('Help', $settings->get('store_footer_col_2_title_translations')['en']);

        App::setLocale('en');
        $footer = app(FrontStoreSettingsService::class)->footer();
        $this->assertSame('Help', $footer['link_columns'][1]['title']);
        $this->assertSame('Contact', $footer['link_columns'][1]['links'][0]['label']);
        $this->assertSame('/returns-and-claims', $footer['link_columns'][1]['links'][1]['url']);
    }

    public function test_webp_generation_only_targets_active_product_media(): void
    {
        Queue::fake();

        $admin = $this->makeUserWithRole('superadmin');
        $activeProduct = $this->makeProduct($admin, 'WEBP-ACTIVE', true);
        $inactiveProduct = $this->makeProduct($admin, 'WEBP-INACTIVE', false);
        $activeMedia = $this->makeProductMedia($activeProduct);
        $inactiveMedia = $this->makeProductMedia($inactiveProduct);

        Cache::forget('settings.store.webp_generation.active_products.'.$admin->id);
        Cache::forget('settings.store.webp_coverage.active_products');

        Livewire::actingAs($admin)
            ->test(StoreSettings::class)
            ->set('tab', 'images')
            ->call('startWebpGeneration')
            ->assertSet('webpGeneration.total', 1)
            ->assertSet('webpGeneration.processed', 0);

        $state = Cache::get('settings.store.webp_generation.active_products.'.$admin->id);
        $pendingIds = array_values(array_map('intval', (array) ($state['pending_ids'] ?? [])));

        $this->assertSame([(int) $activeMedia->id], $pendingIds);
        $this->assertNotContains((int) $inactiveMedia->id, $pendingIds);
        Queue::assertPushed(GenerateWebpConversionsJob::class);
    }

    private function makeUserWithRole(string $role): User
    {
        $user = User::factory()->create();

        Bouncer::role()->firstOrCreate(['name' => 'superadmin']);
        Bouncer::role()->firstOrCreate(['name' => 'admin']);
        Bouncer::role()->firstOrCreate(['name' => 'editor']);
        Bouncer::role()->firstOrCreate(['name' => 'customer']);

        Bouncer::assign($role)->to($user);

        return $user;
    }

    private function makeProduct(User $user, string $code, bool $isActive): Product
    {
        return Product::query()->create([
            'code' => $code,
            'sku' => $code.'-SKU',
            'is_active' => $isActive,
            'manufacturer_id' => null,
            'tax_rate_id' => null,
            'base_price' => 10,
            'stock_qty' => 5,
            'payload' => null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    private function makeProductMedia(Product $product): Media
    {
        return Media::query()->create([
            'model_type' => Product::class,
            'model_id' => $product->id,
            'collection_name' => 'product_main',
            'name' => $product->code,
            'file_name' => strtolower($product->code).'.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 100,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
            'order_column' => 1,
        ]);
    }
}
