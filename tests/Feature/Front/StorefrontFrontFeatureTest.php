<?php

namespace Tests\Feature\Front;

use App\Models\Catalog\Category\Category;
use App\Models\Catalog\Category\CategoryTranslation;
use App\Models\Catalog\Manufacturer\Manufacturer;
use App\Models\Catalog\Manufacturer\ManufacturerTranslation;
use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductTranslation;
use App\Models\Content\Blog\BlogPost;
use App\Models\Content\Blog\BlogPostTranslation;
use App\Models\Content\Page\InfoPage;
use App\Models\Content\Page\InfoPageTranslation;
use App\Models\Settings\Local\Currency;
use App\Models\Settings\Local\OrderStatus;
use App\Models\Settings\Local\PaymentMethod;
use App\Models\Settings\Local\ShippingMethod;
use App\Models\User;
use App\Services\Front\NavigationMenuService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontFrontFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_pretty_storefront_routes_are_available(): void
    {
        [$category, $categorySlug] = $this->seedCategory();
        [$product, $productSlug] = $this->seedProduct($category->id);
        [$manufacturer, $manufacturerSlug] = $this->seedManufacturer();
        [$post, $postSlug] = $this->seedBlogPost();
        [$page, $pageSlug] = $this->seedInfoPage();

        app(SystemSettingsService::class)->putMany([
            'catalog_use_manufacturers' => true,
            'catalog_use_blog' => true,
        ]);

        $this->get('/shop')->assertOk();
        $this->get('/categories')->assertOk();
        $this->get('/category/'.$categorySlug)->assertOk();
        $this->get('/product/'.$productSlug)->assertOk();
        $this->get('/manufacturers')->assertOk();
        $this->get('/manufacturer/'.$manufacturerSlug)->assertOk();
        $this->get('/blog')->assertOk();
        $this->get('/blog/'.$postSlug)->assertOk();
        $this->get('/page/'.$pageSlug)->assertOk();
        $this->get('/contact')->assertOk();
        $this->get('/cart')->assertOk();

        $this->assertNotNull($product);
        $this->assertNotNull($manufacturer);
        $this->assertNotNull($post);
        $this->assertNotNull($page);
    }

    public function test_contact_form_stores_message(): void
    {
        $this->post('/contact', [
            'name' => 'Front Tester',
            'email' => 'front@example.test',
            'phone' => '+38591000000',
            'subject' => 'Wholesale inquiry',
            'message' => 'Please contact me with available B2B pricing details.',
        ])->assertRedirect('/contact');

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'front@example.test',
            'subject' => 'Wholesale inquiry',
            'status' => 'new',
        ]);
    }

    public function test_home_renders_configured_navigation_with_subcategories(): void
    {
        [$parent, $parentSlug] = $this->seedCategory();

        $child = Category::query()->create([
            'scope' => Category::SCOPE_CATALOG,
            'code' => 'child-'.strtolower((string) str()->random(5)),
            'is_active' => true,
            'show_in_menu' => true,
            'sort_order' => 2,
            'parent_id' => $parent->id,
        ]);

        CategoryTranslation::query()->create([
            'category_id' => $child->id,
            'scope' => Category::SCOPE_CATALOG,
            'locale' => 'en',
            'name' => 'Child menu category',
            'slug' => 'child-menu-category',
            'description' => 'Child description',
        ]);

        app(SystemSettingsService::class)->put(NavigationMenuService::SETTINGS_KEY, [
            [
                'type' => 'category',
                'label' => 'Hrana i namirnice',
                'category_id' => $parent->id,
                'page_id' => 0,
                'url' => '',
                'open_in_new_tab' => false,
                'show_dropdown' => true,
                'is_active' => true,
                'sort_order' => 0,
            ],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Hrana i namirnice')
            ->assertSee('Child menu category')
            ->assertSee('/category/'.$parentSlug, false);
    }

    public function test_account_routes_are_prefixed_and_require_authenticated_verified_user(): void
    {
        $this->get('/account')->assertRedirect('/login');
        $this->get('/account/orders')->assertRedirect('/login');
        $this->get('/account/profile')->assertRedirect('/login');

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->get('/account')->assertOk();
        $this->actingAs($user)->get('/account/orders')->assertOk();
        $this->actingAs($user)->get('/account/profile')->assertOk();
    }

    public function test_checkout_creates_order_with_pretty_checkout_routes(): void
    {
        [$category] = $this->seedCategory();
        [$product] = $this->seedProduct($category->id);

        Currency::query()->create([
            'code' => 'EUR',
            'name' => 'Euro',
            'symbol' => 'EUR',
            'symbol_position' => 'left',
            'decimal_places' => 2,
            'exchange_rate' => 1,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        OrderStatus::query()->create([
            'code' => 'new',
            'name' => 'New',
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        ShippingMethod::query()->create([
            'code' => 'standard',
            'name' => 'Standard Shipping',
            'price' => 4.99,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        PaymentMethod::query()->create([
            'code' => 'bank',
            'name' => 'Bank Transfer',
            'provider' => 'bank',
            'fee_type' => 'fixed',
            'fee_value' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->post('/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertRedirect();

        $response = $this->post('/checkout', [
            'customer_first_name' => 'Jane',
            'customer_last_name' => 'Doe',
            'customer_email' => 'jane@example.test',
            'customer_phone' => '+38591000002',

            'billing_first_name' => 'Jane',
            'billing_last_name' => 'Doe',
            'billing_company' => 'AG Test',
            'billing_oib' => '12345678901',
            'billing_vat_id' => 'HR12345678901',
            'billing_address_line_1' => 'Main Street 1',
            'billing_address_line_2' => '',
            'billing_postal_code' => '10000',
            'billing_city' => 'Zagreb',
            'billing_state' => 'Grad Zagreb',
            'billing_country_code' => 'HR',

            'use_billing_for_shipping' => '1',

            'shipping_method_code' => 'standard',
            'payment_method_code' => 'bank',
            'customer_note' => 'Please ring bell on delivery.',
            'accept_terms' => '1',
        ]);

        $order = \App\Models\Sales\Order\Order::query()->first();

        $this->assertNotNull($order);

        $response->assertRedirect(route('checkout.success', ['orderNumber' => $order->order_number]));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_email' => 'jane@example.test',
            'item_qty' => 2,
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->assertDatabaseMissing('orders', [
            'customer_email' => 'missing@example.test',
        ]);
    }

    /**
     * @return array{Category,string}
     */
    private function seedCategory(): array
    {
        $category = Category::query()->create([
            'scope' => Category::SCOPE_CATALOG,
            'code' => 'cat-'.strtolower((string) str()->random(6)),
            'is_active' => true,
            'show_in_menu' => true,
            'sort_order' => 1,
        ]);

        $slug = 'category-'.strtolower((string) str()->random(6));

        CategoryTranslation::query()->create([
            'category_id' => $category->id,
            'scope' => Category::SCOPE_CATALOG,
            'locale' => 'en',
            'name' => 'Category '.$slug,
            'slug' => $slug,
            'description' => 'Category description',
        ]);

        return [$category, $slug];
    }

    /**
     * @return array{Product,string}
     */
    private function seedProduct(int $categoryId): array
    {
        $product = Product::query()->create([
            'code' => 'prod-'.strtolower((string) str()->random(6)),
            'sku' => 'SKU-'.strtoupper((string) str()->random(5)),
            'is_active' => true,
            'base_price' => 49.99,
            'stock_qty' => 15,
        ]);

        $slug = 'product-'.strtolower((string) str()->random(6));

        ProductTranslation::query()->create([
            'product_id' => $product->id,
            'locale' => 'en',
            'name' => 'Product '.$slug,
            'slug' => $slug,
            'excerpt' => 'Product excerpt',
            'description' => 'Product body',
        ]);

        $product->categories()->sync([$categoryId => ['sort_order' => 1, 'is_primary' => true]]);

        return [$product, $slug];
    }

    /**
     * @return array{Manufacturer,string}
     */
    private function seedManufacturer(): array
    {
        $manufacturer = Manufacturer::query()->create([
            'code' => 'man-'.strtolower((string) str()->random(6)),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $slug = 'manufacturer-'.strtolower((string) str()->random(6));

        ManufacturerTranslation::query()->create([
            'manufacturer_id' => $manufacturer->id,
            'locale' => 'en',
            'name' => 'Manufacturer '.$slug,
            'slug' => $slug,
            'description' => 'Manufacturer description',
        ]);

        return [$manufacturer, $slug];
    }

    /**
     * @return array{BlogPost,string}
     */
    private function seedBlogPost(): array
    {
        $post = BlogPost::query()->create([
            'code' => 'blog-'.strtolower((string) str()->random(6)),
            'is_active' => true,
            'published_at' => now()->subDay(),
            'sort_order' => 1,
        ]);

        $slug = 'blog-'.strtolower((string) str()->random(6));

        BlogPostTranslation::query()->create([
            'post_id' => $post->id,
            'locale' => 'en',
            'title' => 'Blog '.$slug,
            'slug' => $slug,
            'excerpt' => 'Blog excerpt',
            'body_html' => '<p>Blog body</p>',
        ]);

        return [$post, $slug];
    }

    /**
     * @return array{InfoPage,string}
     */
    private function seedInfoPage(): array
    {
        $page = InfoPage::query()->create([
            'code' => 'page-'.strtolower((string) str()->random(6)),
            'layout' => 'default',
            'is_active' => true,
            'published_at' => now()->subDay(),
            'sort_order' => 1,
        ]);

        $slug = 'page-'.strtolower((string) str()->random(6));

        InfoPageTranslation::query()->create([
            'page_id' => $page->id,
            'locale' => 'en',
            'title' => 'Page '.$slug,
            'slug' => $slug,
            'excerpt' => 'Page excerpt',
            'body_html' => '<p>Page body</p>',
        ]);

        return [$page, $slug];
    }
}
