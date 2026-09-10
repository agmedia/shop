<?php

namespace Tests\Feature\Front;

use App\Http\Controllers\Front\CatalogController;
use App\Mail\NewsletterCouponMail;
use App\Models\Catalog\Action\CatalogAction;
use App\Models\Catalog\Attribute\Attribute;
use App\Models\Catalog\Category\Category;
use App\Models\Catalog\Category\CategoryTranslation;
use App\Models\Catalog\Manufacturer\Manufacturer;
use App\Models\Catalog\Manufacturer\ManufacturerTranslation;
use App\Models\Catalog\Option\Option;
use App\Models\Catalog\Option\OptionValue;
use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductOptionValue;
use App\Models\Catalog\Product\ProductTranslation;
use App\Models\Content\Blog\BlogPost;
use App\Models\Content\Blog\BlogPostTranslation;
use App\Models\Content\ContentBlock;
use App\Models\Content\Page\InfoPage;
use App\Models\Content\Page\InfoPageTranslation;
use App\Models\Content\Support\Comment;
use App\Models\Settings\Local\Currency;
use App\Models\Settings\Local\OrderStatus;
use App\Models\Settings\Local\PaymentMethod;
use App\Models\Settings\Local\ShippingMethod;
use App\Models\User;
use App\Services\Front\NavigationMenuService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Silber\Bouncer\BouncerFacade as Bouncer;
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

    public function test_cart_coupon_applies_cart_discount_to_items_only(): void
    {
        $this->useEnglishStorefrontLocale();

        [$category] = $this->seedCategory();
        [$product] = $this->seedProduct($category->id);

        $product->update([
            'base_price' => 100.00,
            'stock_qty' => 10,
        ]);

        CatalogAction::query()->create([
            'code' => 'cart-bali-10',
            'scope' => CatalogAction::SCOPE_CART,
            'type' => CatalogAction::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'target_type' => CatalogAction::TARGET_ALL,
            'audience_type' => CatalogAction::AUDIENCE_ALL,
            'coupon_code' => 'BALI10',
            'min_subtotal' => 0.01,
            'is_active' => true,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        $this->post('/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertRedirect();

        $this->post('/cart/coupon', [
            'coupon_code' => 'bali10',
        ])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('status', __('ui.cart.status.coupon_applied'));

        $summary = app(\App\Services\Front\CartService::class)->summary();

        $this->assertSame(200.0, (float) $summary['subtotal']);
        $this->assertSame(20.0, (float) $summary['cart_discount_total']);
        $this->assertSame(20.0, (float) $summary['discount_total']);
        $this->assertSame(180.0, (float) $summary['grand_total']);
    }

    public function test_desktop_announcement_bar_uses_saved_scroll_and_color_settings(): void
    {
        app(SystemSettingsService::class)->putMany([
            'store_announcement_enabled' => true,
            'store_announcement_text' => 'Spring promo',
            'store_announcement_scroll_enabled' => true,
            'store_announcement_scroll_duration_seconds' => 24,
            'store_announcement_background_color' => '#123456',
            'store_announcement_text_color' => '#abcdef',
        ]);

        $this
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            ])
            ->get('/')
            ->assertOk()
            ->assertSee('Spring promo')
            ->assertSee('store-announcement-bar is-scrolling', false)
            ->assertSee('style="background-color: #123456; color: #abcdef; --store-announcement-duration: 24s;"', false)
            ->assertSee('animation: store-announcement-marquee var(--store-announcement-duration, 18s) linear infinite;', false)
            ->assertSee('@keyframes store-announcement-marquee', false);
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

    public function test_return_request_form_is_available_at_seo_url(): void
    {
        $this->get('/forma-za-povrat-i-reklamacije')
            ->assertOk()
            ->assertSee((string) __('return_request.form.email'))
            ->assertSee((string) __('return_request.form.order_number'))
            ->assertSee((string) __('return_request.form.return_items'))
            ->assertSee((string) __('return_request.form.note'));
    }

    public function test_return_request_form_stores_message_and_uses_orders_email(): void
    {
        app(SystemSettingsService::class)->putMany([
            'store_email_enabled' => true,
            'store_email_orders_to' => 'orders@example.test',
            'store_email_contact_to' => 'support@example.test',
        ]);

        Mail::shouldReceive('raw')
            ->once()
            ->with(
                \Mockery::on(static fn (string $body): bool => str_contains($body, 'R-1001')
                    && str_contains($body, 'T-shirt size M')),
                \Mockery::on(static function (callable $callback): bool {
                    $mail = new class
                    {
                        public array $calls = [];

                        public function to(string $email): self
                        {
                            $this->calls['to'] = $email;

                            return $this;
                        }

                        public function subject(string $subject): self
                        {
                            $this->calls['subject'] = $subject;

                            return $this;
                        }

                        public function replyTo(string $email): self
                        {
                            $this->calls['reply_to'] = $email;

                            return $this;
                        }
                    };

                    $callback($mail);

                    return ($mail->calls['to'] ?? '') === 'orders@example.test'
                        && ($mail->calls['reply_to'] ?? '') === 'buyer@example.test'
                        && str_contains((string) ($mail->calls['subject'] ?? ''), 'R-1001');
                })
            );

        $this->post('/forma-za-povrat-i-reklamacije', [
            'email' => 'buyer@example.test',
            'order_number' => 'R-1001',
            'return_items' => 'T-shirt size M',
            'note' => 'Wrong size.',
        ])
            ->assertRedirect('/forma-za-povrat-i-reklamacije')
            ->assertSessionHas('status', (string) __('return_request.sent_status'));

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'buyer@example.test',
            'subject' => __('return_request.mail.subject', ['order' => 'R-1001']),
            'status' => 'new',
        ]);
    }

    public function test_return_request_form_uses_enabled_recaptcha_settings(): void
    {
        app(SystemSettingsService::class)->putMany([
            'store_captcha_recaptcha_v3_enabled' => true,
            'store_captcha_recaptcha_v3_site_key' => 'test-site-key',
            'store_captcha_recaptcha_v3_secret_key' => 'test-secret-key',
            'store_captcha_recaptcha_v3_min_score' => 0.7,
        ]);

        $this->get('/forma-za-povrat-i-reklamacije')
            ->assertOk()
            ->assertSee('data-recaptcha-site-key="test-site-key"', false)
            ->assertSee('data-recaptcha-action="return_request_form"', false)
            ->assertSee('https://www.google.com/recaptcha/api.js?render=test-site-key', false);

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.9,
                'action' => 'return_request_form',
            ]),
        ]);

        $this->post('/forma-za-povrat-i-reklamacije', [
            'email' => 'captcha-buyer@example.test',
            'order_number' => 'R-2002',
            'return_items' => 'Socks size L',
            'note' => '',
            'recaptcha_token' => 'token-123',
        ])->assertRedirect('/forma-za-povrat-i-reklamacije');

        Http::assertSent(static fn ($request): bool => $request->url() === 'https://www.google.com/recaptcha/api/siteverify'
            && $request['secret'] === 'test-secret-key'
            && $request['response'] === 'token-123');

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'captcha-buyer@example.test',
            'subject' => __('return_request.mail.subject', ['order' => 'R-2002']),
            'status' => 'new',
        ]);
    }

    public function test_front_auth_forms_use_enabled_recaptcha_settings(): void
    {
        app(SystemSettingsService::class)->putMany([
            'store_captcha_recaptcha_v3_enabled' => true,
            'store_captcha_recaptcha_v3_site_key' => 'test-site-key',
            'store_captcha_recaptcha_v3_secret_key' => 'test-secret-key',
            'store_captcha_recaptcha_v3_min_score' => 0.7,
        ]);

        $this->get('/auth/login')
            ->assertOk()
            ->assertSee('data-recaptcha-site-key="test-site-key"', false)
            ->assertSee('data-recaptcha-action="login_form"', false)
            ->assertSee('https://www.google.com/recaptcha/api.js?render=test-site-key', false);

        $this->get('/auth/register')
            ->assertOk()
            ->assertSee('data-recaptcha-site-key="test-site-key"', false)
            ->assertSee('data-recaptcha-action="register_form"', false)
            ->assertSee('https://www.google.com/recaptcha/api.js?render=test-site-key', false);

        $mobileHeaders = [
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        ];

        $this
            ->withHeaders($mobileHeaders)
            ->get('/auth/login')
            ->assertOk()
            ->assertSee('data-recaptcha-action="login_form"', false);

        $this
            ->withHeaders($mobileHeaders)
            ->get('/auth/register')
            ->assertOk()
            ->assertSee('data-recaptcha-action="register_form"', false);
    }

    public function test_front_login_validates_enabled_recaptcha(): void
    {
        app(SystemSettingsService::class)->putMany([
            'store_captcha_recaptcha_v3_enabled' => true,
            'store_captcha_recaptcha_v3_site_key' => 'test-site-key',
            'store_captcha_recaptcha_v3_secret_key' => 'test-secret-key',
            'store_captcha_recaptcha_v3_min_score' => 0.7,
        ]);

        $user = User::factory()->create();

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.9,
                'action' => 'login_form',
            ]),
        ]);

        $this->post('/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'recaptcha_token' => 'login-token',
        ])->assertRedirect(route('account.dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);

        Http::assertSent(static fn ($request): bool => $request->url() === 'https://www.google.com/recaptcha/api/siteverify'
            && $request['secret'] === 'test-secret-key'
            && $request['response'] === 'login-token');
    }

    public function test_front_register_validates_enabled_recaptcha(): void
    {
        app(SystemSettingsService::class)->putMany([
            'store_captcha_recaptcha_v3_enabled' => true,
            'store_captcha_recaptcha_v3_site_key' => 'test-site-key',
            'store_captcha_recaptcha_v3_secret_key' => 'test-secret-key',
            'store_captcha_recaptcha_v3_min_score' => 0.7,
        ]);

        Bouncer::role()->firstOrCreate(['name' => 'customer'], ['title' => 'Customer']);

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.9,
                'action' => 'register_form',
            ]),
        ]);

        $this->post('/auth/register', [
            'first_name' => 'Captcha',
            'last_name' => 'Buyer',
            'email' => 'captcha-buyer@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'recaptcha_token' => 'register-token',
        ])->assertRedirect(route('account.dashboard', absolute: false));

        $this->assertAuthenticated();

        Http::assertSent(static fn ($request): bool => $request->url() === 'https://www.google.com/recaptcha/api/siteverify'
            && $request['secret'] === 'test-secret-key'
            && $request['response'] === 'register-token');

        $this->assertDatabaseHas('users', [
            'name' => 'Captcha Buyer',
            'email' => 'captcha-buyer@example.test',
        ]);
    }

    public function test_shop_search_matches_product_sku(): void
    {
        [$category] = $this->seedCategory();
        [$product] = $this->seedProduct($category->id);

        $product->update(['sku' => 'FIND-ME-123']);

        $query = Product::query()->where('is_active', true);

        $controller = app(CatalogController::class);
        $method = new \ReflectionMethod($controller, 'applyProductSearch');
        $method->setAccessible(true);
        $method->invoke($controller, $query, 'en', 'en', 'FIND-ME-123');

        $this->assertSame([$product->id], $query->pluck('id')->all());
    }

    public function test_category_search_matches_product_sku(): void
    {
        [$category] = $this->seedCategory();
        [$product] = $this->seedProduct($category->id);

        $product->update(['sku' => 'CAT-FIND-456']);

        $query = Product::query()
            ->where('is_active', true)
            ->whereHas('categories', fn ($categoryQuery) => $categoryQuery->whereKey($category->id));

        $controller = app(CatalogController::class);
        $method = new \ReflectionMethod($controller, 'applyProductSearch');
        $method->setAccessible(true);
        $method->invoke($controller, $query, 'en', 'en', 'CAT-FIND-456');

        $this->assertSame([$product->id], $query->pluck('id')->all());
    }

    public function test_category_pagination_uses_the_exact_configured_page_size_with_five_columns(): void
    {
        $this->useEnglishStorefrontLocale();

        [$category, $categorySlug] = $this->seedCategory();

        foreach (range(1, 25) as $index) {
            $this->seedProduct($category->id);
        }

        app(SystemSettingsService::class)->put('front_category_products_per_page_desktop', 24);

        $response = $this->get('/category/'.$categorySlug.'?cols=5');

        $response->assertOk();

        $products = $response->viewData('products');

        $this->assertSame(24, $products->perPage());
        $this->assertCount(24, $products->items());
        $this->assertSame(2, $products->lastPage());
    }

    public function test_search_autocomplete_is_not_available_when_disabled(): void
    {
        app(SystemSettingsService::class)->put('store_search_autocomplete_enabled', false);

        $this->getJson('/search/autocomplete?q=prod')
            ->assertNotFound();
    }

    public function test_search_autocomplete_returns_product_name_sku_price_and_old_price_when_available(): void
    {
        $this->useEnglishStorefrontLocale();
        app(SystemSettingsService::class)->put('store_search_autocomplete_enabled', true);

        [$category] = $this->seedCategory();
        [$product] = $this->seedProduct($category->id);

        $product->update([
            'sku' => 'AUTO-123',
            'base_price' => 100,
        ]);

        $product->translations()->where('locale', 'en')->update([
            'name' => 'Autocomplete Product',
            'excerpt' => 'Autocomplete excerpt',
            'description' => 'Autocomplete description',
        ]);

        $action = CatalogAction::query()->create([
            'code' => 'auto-'.strtolower((string) str()->random(6)),
            'scope' => CatalogAction::SCOPE_PRODUCT,
            'type' => CatalogAction::TYPE_PERCENTAGE,
            'discount_value' => 20,
            'target_type' => CatalogAction::TARGET_PRODUCT,
            'audience_type' => CatalogAction::AUDIENCE_ALL,
            'is_active' => true,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        $action->targets()->create([
            'target_type' => CatalogAction::TARGET_PRODUCT,
            'target_id' => $product->id,
        ]);

        $this->getJson('/search/autocomplete?q=Auto')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.name', 'Autocomplete Product')
            ->assertJsonPath('items.0.sku', 'AUTO-123')
            ->assertJsonPath('items.0.price', '80.00 €')
            ->assertJsonPath('items.0.old_price', '100.00 €')
            ->assertJsonPath('items.0.has_discount', true);
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

    public function test_home_renders_instagram_curated_grid_block(): void
    {
        $block = ContentBlock::query()->create([
            'code' => 'home-instagram-widget',
            'name' => 'Home Instagram Widget',
            'type' => 'instagram_curated_grid',
            'is_active' => true,
            'payload' => null,
        ]);

        $block->translations()->create([
            'locale' => 'hr',
            'title' => 'Prati nas na Instagramu',
            'subtitle' => '@kozo_bodywear',
            'body_html' => null,
            'cta_label' => 'Otvori profil',
            'cta_url' => 'https://www.instagram.com/kozo_bodywear/',
            'payload' => null,
        ]);

        $block->translations()->create([
            'locale' => 'en',
            'title' => 'Follow us on Instagram',
            'subtitle' => '@kozo_bodywear',
            'body_html' => null,
            'cta_label' => 'Open profile',
            'cta_url' => 'https://www.instagram.com/kozo_bodywear/',
            'payload' => null,
        ]);

        $block->slots()->create([
            'placement' => 'home.bottom',
            'frontend_variant' => 'all',
            'target_type' => null,
            'target_ref' => null,
            'sort_order' => 999,
            'is_active' => true,
        ]);

        $image = UploadedFile::fake()->image('instagram-widget.jpg', 1080, 1080);

        $block->addMedia($image->getPathname())
            ->usingName('Instagram widget image')
            ->usingFileName('instagram-widget.jpg')
            ->withCustomProperties([
                'link_url' => ['hr' => 'https://www.instagram.com/p/demo-post/', 'en' => 'https://www.instagram.com/p/demo-post/'],
                'link_url_value' => 'https://www.instagram.com/p/demo-post/',
                'caption' => ['hr' => 'Demo Instagram post caption', 'en' => 'Demo Instagram post caption'],
            ])
            ->toMediaCollection('block_slides');

        $this->get('/')
            ->assertOk()
            ->assertSee('Prati nas na Instagramu')
            ->assertSee('@kozo_bodywear')
            ->assertSee('https://www.instagram.com/kozo_bodywear/', false)
            ->assertSee('https://www.instagram.com/p/demo-post/', false);
    }

    public function test_home_renders_material_and_craftsmanship_block(): void
    {
        $block = ContentBlock::query()->create([
            'code' => 'home-material-craftsmanship',
            'name' => 'Home Material & Craftsmanship',
            'type' => 'material_craftsmanship',
            'is_active' => true,
            'payload' => null,
        ]);

        $block->translations()->create([
            'locale' => 'hr',
            'title' => 'Micromodal ili Giza pamuk?',
            'subtitle' => 'Dva premium osjećaja za kupce koji žele birati prema materijalu, ne samo prema modelu.',
            'body_html' => null,
            'cta_label' => 'Pogledaj premium modele',
            'cta_url' => '/shop',
            'payload' => [
                'material_craftsmanship' => [
                    'expand_label' => 'Vidi više',
                    'materials' => [
                        'micromodal' => [
                            'eyebrow' => 'Za svilenkast feel',
                            'title' => 'Micromodal',
                            'intro' => 'Lagan, vrlo mekan i elastičan materijal.',
                            'body_1' => 'Micromodal detalj prvi.',
                            'body_2' => 'Micromodal detalj drugi.',
                            'bullets' => ['svilenkast dodir', 'vrlo elastičan', 'hipoalergen'],
                        ],
                        'giza' => [
                            'eyebrow' => 'Za clean cotton feel',
                            'title' => 'Giza pamuk',
                            'intro' => 'Fini egipatski pamuk.',
                            'body_1' => 'Giza detalj prvi.',
                            'body_2' => 'Giza detalj drugi.',
                            'bullets' => ['prozračan osjećaj', 'izrazito upijajući', 'dugotrajno pletivo'],
                        ],
                    ],
                ],
            ],
        ]);

        $block->slots()->create([
            'placement' => 'home.after_products',
            'frontend_variant' => 'desktop',
            'target_type' => null,
            'target_ref' => null,
            'sort_order' => 220,
            'is_active' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Micromodal ili Giza pamuk?')
            ->assertSee('Micromodal')
            ->assertSee('Giza pamuk')
            ->assertSee('Vidi više')
            ->assertSee('/shop', false);
    }

    public function test_material_and_craftsmanship_block_uses_payload_texts_and_uploaded_icons(): void
    {
        $block = ContentBlock::query()->create([
            'code' => 'home-material-craftsmanship-custom',
            'name' => 'Home Material & Craftsmanship Custom',
            'type' => 'material_craftsmanship',
            'is_active' => true,
            'payload' => null,
        ]);

        $block->translations()->create([
            'locale' => 'hr',
            'title' => 'Materijali iz admina',
            'subtitle' => 'Podnaslov iz admina.',
            'body_html' => null,
            'cta_label' => null,
            'cta_url' => null,
            'payload' => [
                'material_craftsmanship' => [
                    'expand_label' => 'Otvori detalje',
                    'materials' => [
                        'micromodal' => [
                            'eyebrow' => 'Admin modal eyebrow',
                            'title' => 'Admin micromodal',
                            'intro' => '',
                            'body_1' => 'Admin micromodal prvi tekst.',
                            'body_2' => 'Admin micromodal drugi tekst.',
                            'bullets' => [
                                'Admin svilenkast dodir',
                                'Admin elasticnost',
                                'Admin hipoalergen',
                            ],
                        ],
                        'giza' => [
                            'eyebrow' => 'Admin giza eyebrow',
                            'title' => 'Admin Giza',
                            'intro' => 'Admin giza uvod.',
                            'body_1' => 'Admin giza prvi tekst.',
                            'body_2' => 'Admin giza drugi tekst.',
                            'bullets' => [
                                'Admin prozracan',
                                'Admin upojnost',
                                'Admin dugotrajno',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $block->slots()->create([
            'placement' => 'home.after_products',
            'frontend_variant' => 'desktop',
            'target_type' => null,
            'target_ref' => null,
            'sort_order' => 220,
            'is_active' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Materijali iz admina')
            ->assertSee('Otvori detalje')
            ->assertSee('Admin micromodal')
            ->assertSee('Admin dugotrajno')
            ->assertDontSee('Lagan, vrlo mekan i elastičan materijal za one koji žele gladak osjećaj uz tijelo.')
            ->assertSee('front-theme/images/GIZA_PAMUK.svg', false)
            ->assertSee('front-theme/images/MIKROMODAL.svg', false)
            ->assertSee('front-theme/images/SVILENKASTI_DODIR.svg', false)
            ->assertSee('front-theme/images/DUGOTRAJAN.svg', false);
    }

    public function test_products_carousel_keeps_blank_subtitle_empty(): void
    {
        $this->useEnglishStorefrontLocale();

        [$category] = $this->seedCategory();
        [$product] = $this->seedProduct($category->id);

        $image = UploadedFile::fake()->image('carousel-product.jpg', 1200, 1600);
        $product->addMedia($image->getPathname())
            ->usingName('carousel-product')
            ->usingFileName('carousel-product.jpg')
            ->toMediaCollection('product_main');

        $block = ContentBlock::query()->create([
            'code' => 'home-products-blank-subtitle',
            'name' => 'Home Products Blank Subtitle',
            'type' => 'products_carousel',
            'is_active' => true,
            'payload' => null,
        ]);

        $block->translations()->create([
            'locale' => 'en',
            'title' => 'New arrivals',
            'subtitle' => '',
            'body_html' => null,
            'cta_label' => null,
            'cta_url' => null,
            'payload' => null,
        ]);

        $block->translations()->create([
            'locale' => 'hr',
            'title' => 'Novo',
            'subtitle' => 'Fallback subtitle',
            'body_html' => null,
            'cta_label' => null,
            'cta_url' => null,
            'payload' => null,
        ]);

        $block->items()->create([
            'item_type' => 'product',
            'item_id' => $product->id,
            'sort_order' => 1,
        ]);

        $block->slots()->create([
            'placement' => 'home.after_products',
            'frontend_variant' => 'desktop',
            'target_type' => null,
            'target_ref' => null,
            'sort_order' => 10,
            'is_active' => true,
        ]);

        app(SystemSettingsService::class)->put('store_product_desktop_default_cols', 5);

        $this->get('/')
            ->assertOk()
            ->assertSee('New arrivals')
            ->assertSee('data-products-carousel-splide', false)
            ->assertSee('const preferredDesktopPerPage = 5;', false)
            ->assertDontSee('Fallback subtitle');

        app(SystemSettingsService::class)->put('store_product_desktop_default_cols', 4);

        $this->get('/?cols=5')
            ->assertOk()
            ->assertSee('const preferredDesktopPerPage = 5;', false);
    }

    public function test_home_renders_footer_newsletter_validation_hooks(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-newsletter-form', false)
            ->assertSee('data-newsletter-email', false)
            ->assertSee('data-newsletter-error', false)
            ->assertSee((string) __('ui.front.desktop.newsletter.validation.email_required'))
            ->assertSee((string) __('ui.front.desktop.newsletter.validation.email_invalid'))
            ->assertSee((string) __('ui.front.desktop.newsletter.validation.accept_terms'));
    }

    public function test_newsletter_form_requires_email_and_gdpr_consent(): void
    {
        $this->from('/')
            ->post('/newsletter/subscribe', [])
            ->assertRedirect('/')
            ->assertSessionHasErrorsIn('newsletter', [
                'newsletter_email',
                'newsletter_accept_terms',
            ]);
    }

    public function test_newsletter_form_stores_signup_when_database_provider_is_selected(): void
    {
        $this->createNewsletterSignupsTable();

        app(SystemSettingsService::class)->putMany([
            'store_newsletter_provider' => 'database',
        ]);

        $this->from('/')
            ->post('/newsletter/subscribe', [
                'newsletter_email' => 'newsletter@example.test',
                'newsletter_accept_terms' => '1',
            ])
            ->assertRedirect('/')
            ->assertSessionHas('status', (string) __('ui.front.desktop.newsletter.status.subscribed'));

        $this->assertDatabaseHas('newsletter_signups', [
            'email' => 'newsletter@example.test',
            'provider' => 'database',
            'sync_status' => 'synced',
            'consent_accepted' => 1,
        ]);
    }

    public function test_footer_newsletter_uses_enabled_recaptcha_settings(): void
    {
        app(SystemSettingsService::class)->putMany([
            'store_captcha_recaptcha_v3_enabled' => true,
            'store_captcha_recaptcha_v3_site_key' => 'test-site-key',
            'store_captcha_recaptcha_v3_secret_key' => 'test-secret-key',
            'store_captcha_recaptcha_v3_min_score' => 0.7,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-recaptcha-site-key="test-site-key"', false)
            ->assertSee('data-recaptcha-action="newsletter_footer"', false)
            ->assertSee('https://www.google.com/recaptcha/api.js?render=test-site-key', false);
    }

    public function test_newsletter_form_validates_enabled_recaptcha(): void
    {
        $this->createNewsletterSignupsTable();

        app(SystemSettingsService::class)->putMany([
            'store_newsletter_provider' => 'database',
            'store_captcha_recaptcha_v3_enabled' => true,
            'store_captcha_recaptcha_v3_site_key' => 'test-site-key',
            'store_captcha_recaptcha_v3_secret_key' => 'test-secret-key',
            'store_captcha_recaptcha_v3_min_score' => 0.7,
        ]);

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.9,
                'action' => 'newsletter_footer',
            ]),
        ]);

        $this->postJson('/newsletter/subscribe', [
            'newsletter_email' => 'captcha-newsletter@example.test',
            'newsletter_accept_terms' => '1',
            'recaptcha_token' => 'newsletter-token',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        Http::assertSent(static fn ($request): bool => $request->url() === 'https://www.google.com/recaptcha/api/siteverify'
            && $request['secret'] === 'test-secret-key'
            && $request['response'] === 'newsletter-token');

        $this->assertDatabaseHas('newsletter_signups', [
            'email' => 'captcha-newsletter@example.test',
            'provider' => 'database',
            'sync_status' => 'synced',
        ]);
    }

    public function test_newsletter_form_sends_bali10_coupon_email_when_email_is_enabled(): void
    {
        $this->createNewsletterSignupsTable();

        Mail::fake();

        app(SystemSettingsService::class)->putMany([
            'store_newsletter_provider' => 'database',
            'store_email_enabled' => true,
            'store_brand_name' => 'KOZO',
        ]);

        $this->from('/')
            ->post('/newsletter/subscribe', [
                'newsletter_email' => 'newsletter@example.test',
                'newsletter_accept_terms' => '1',
            ])
            ->assertRedirect('/')
            ->assertSessionHas('status', (string) __('ui.front.desktop.newsletter.status.subscribed'));

        Mail::assertSent(NewsletterCouponMail::class, function (NewsletterCouponMail $mail): bool {
            return $mail->hasTo('newsletter@example.test')
                && $mail->couponCode === 'BALI10'
                && $mail->storeName === 'KOZO'
                && str_contains($mail->render(), 'BALI10');
        });
    }

    public function test_newsletter_form_syncs_mailchimp_without_local_database_storage(): void
    {
        $this->createNewsletterSignupsTable();

        app(SystemSettingsService::class)->putMany([
            'store_newsletter_provider' => 'mailchimp',
            'store_newsletter_mailchimp_api_key' => 'test-key-us6',
            'store_newsletter_mailchimp_list_id' => 'audience-123',
        ]);

        Http::fake([
            'https://us6.api.mailchimp.com/3.0/lists/audience-123/members/*' => Http::response([
                'id' => 'mailchimp-member-1',
            ], 200),
        ]);

        $this->from('/')
            ->post('/newsletter/subscribe', [
                'newsletter_email' => 'newsletter@example.test',
                'newsletter_accept_terms' => '1',
            ])
            ->assertRedirect('/')
            ->assertSessionHas('status', (string) __('ui.front.desktop.newsletter.status.subscribed'));

        $this->assertDatabaseMissing('newsletter_signups', [
            'email' => 'newsletter@example.test',
        ]);

        Http::assertSentCount(1);
    }

    public function test_newsletter_form_returns_json_for_ajax_submission(): void
    {
        $this->createNewsletterSignupsTable();

        app(SystemSettingsService::class)->putMany([
            'store_newsletter_provider' => 'database',
        ]);

        $this->postJson('/newsletter/subscribe', [
            'newsletter_email' => 'ajax-newsletter@example.test',
            'newsletter_accept_terms' => '1',
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJson([
                'message' => (string) __('ui.front.desktop.newsletter.status.subscribed'),
                'type' => 'success',
            ]);

        $this->assertDatabaseHas('newsletter_signups', [
            'email' => 'ajax-newsletter@example.test',
            'provider' => 'database',
            'sync_status' => 'synced',
            'consent_accepted' => 1,
        ]);
    }

    public function test_category_page_includes_sastav_filter_and_filters_products(): void
    {
        $this->useEnglishStorefrontLocale();

        [$category, $categorySlug] = $this->seedCategory();
        [$bambooProduct, $bambooSlug] = $this->seedProduct($category->id);
        [$linenProduct, $linenSlug] = $this->seedProduct($category->id);

        $bambooAttribute = Attribute::query()->create([
            'code' => 'sastav-bamboo',
            'group_code' => 'sastav',
            'type' => Attribute::TYPE_SELECT,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $bambooAttribute->translations()->create([
            'locale' => 'en',
            'group_name' => 'Composition',
            'name' => 'Bamboo',
            'slug' => 'sastav-bamboo',
            'description' => null,
            'payload' => null,
        ]);

        $linenAttribute = Attribute::query()->create([
            'code' => 'sastav-linen',
            'group_code' => 'sastav',
            'type' => Attribute::TYPE_SELECT,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $linenAttribute->translations()->create([
            'locale' => 'en',
            'group_name' => 'Composition',
            'name' => 'Linen',
            'slug' => 'sastav-linen',
            'description' => null,
            'payload' => null,
        ]);

        $bambooProduct->attributes()->attach($bambooAttribute->id, [
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $linenProduct->attributes()->attach($linenAttribute->id, [
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->attachProductSizeOptions($bambooProduct, ['S', 'M']);
        $this->attachProductSizeOptions($linenProduct, ['M', 'L']);

        $bambooName = 'Product '.$bambooSlug;
        $linenName = 'Product '.$linenSlug;

        $this->get('/category/'.$categorySlug)
            ->assertOk()
            ->assertSee('Composition')
            ->assertSee('name="attr_sastav"', false)
            ->assertSee('Bamboo')
            ->assertSee('Linen')
            ->assertSee($bambooName)
            ->assertSee($linenName);

        $categoryResponse = $this->get('/category/'.$categorySlug);
        $categoryHtml = $categoryResponse->getContent();
        $this->assertIsString($categoryHtml);
        $this->assertNotFalse(strpos($categoryHtml, 'name="size"'));
        $this->assertNotFalse(strpos($categoryHtml, 'name="attr_sastav"'));
        $this->assertLessThan(
            strpos($categoryHtml, 'name="attr_sastav"'),
            strpos($categoryHtml, 'name="size"')
        );

        $this->get('/category/'.$categorySlug.'?attr_sastav='.$bambooAttribute->id)
            ->assertOk()
            ->assertSee('Composition')
            ->assertSee('Bamboo')
            ->assertSee($bambooName)
            ->assertDontSee($linenName);
    }

    public function test_product_cards_render_material_attribute_between_name_and_price(): void
    {
        $this->useEnglishStorefrontLocale();

        [$category] = $this->seedCategory();
        [$product, $productSlug] = $this->seedProduct($category->id);
        $material = '95% Micromodal, 5% Elastane';

        $this->attachProductAttribute($product, 'material', 'Material', $material, 1);

        $response = $this->get('/shop')
            ->assertOk()
            ->assertSee($material);

        $html = $response->getContent();
        $this->assertIsString($html);

        $namePosition = strpos($html, 'Product '.$productSlug);
        $materialPosition = strpos($html, $material);
        $pricePosition = strpos($html, '49.99 €');

        $this->assertNotFalse($namePosition);
        $this->assertNotFalse($materialPosition);
        $this->assertNotFalse($pricePosition);
        $this->assertLessThan($materialPosition, $namePosition);
        $this->assertLessThan($pricePosition, $materialPosition);
    }

    public function test_mobile_home_renders_instagram_curated_grid_assets_and_slider_init(): void
    {
        app(SystemSettingsService::class)->put('catalog_use_mobile_view', true);

        $block = ContentBlock::query()->create([
            'code' => 'mobile-instagram-widget',
            'name' => 'Mobile Instagram Widget',
            'type' => 'instagram_curated_grid',
            'is_active' => true,
            'payload' => null,
        ]);

        $block->translations()->create([
            'locale' => 'hr',
            'title' => 'Prati nas na Instagramu',
            'subtitle' => '@kozo_bodywear',
            'body_html' => null,
            'cta_label' => 'Otvori profil',
            'cta_url' => 'https://www.instagram.com/kozo_bodywear/',
            'payload' => null,
        ]);

        $block->slots()->create([
            'placement' => 'home.hero',
            'frontend_variant' => 'mobile',
            'target_type' => null,
            'target_ref' => null,
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $image = UploadedFile::fake()->image('instagram-mobile-widget.jpg', 1080, 1080);

        $block->addMedia($image->getPathname())
            ->usingName('Instagram mobile widget image')
            ->usingFileName('instagram-mobile-widget.jpg')
            ->withCustomProperties([
                'link_url' => ['hr' => 'https://www.instagram.com/p/mobile-demo-post/'],
                'link_url_value' => 'https://www.instagram.com/p/mobile-demo-post/',
                'caption' => ['hr' => 'Demo Instagram mobile post caption'],
            ])
            ->toMediaCollection('block_slides');

        $this
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            ])
            ->get('/')
            ->assertOk()
            ->assertSee('https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/js/splide.min.js', false)
            ->assertSee('data-instagram-grid-splide', false)
            ->assertSee("type: count > 1 ? 'loop' : 'slide'", false)
            ->assertSee("const mobilePaddingRight = count > 1 ? '18%' : '0';", false)
            ->assertSee('perPage: 1,', false)
            ->assertSee("padding: { left: '0', right: mobilePaddingRight },", false);
    }

    public function test_shop_listing_falls_back_to_gallery_when_main_image_file_is_missing(): void
    {
        $this->useEnglishStorefrontLocale();

        [$category] = $this->seedCategory();
        [$product] = $this->seedProduct($category->id);

        $mainImage = UploadedFile::fake()->image('missing-main.jpg', 1200, 1600);
        $galleryImage = UploadedFile::fake()->image('gallery-fallback.jpg', 1200, 1600);

        $mainMedia = $product->addMedia($mainImage->getPathname())
            ->usingName('missing-main')
            ->usingFileName('missing-main.jpg')
            ->toMediaCollection('product_main');

        $galleryMedia = $product->addMedia($galleryImage->getPathname())
            ->usingName('gallery-fallback')
            ->usingFileName('gallery-fallback.jpg')
            ->toMediaCollection('product_gallery');

        @unlink($mainMedia->getPath());

        $this->get('/shop')
            ->assertOk()
            ->assertSee('gallery-fallback.jpg', false)
            ->assertSee('data-product-image="'.$galleryMedia->getUrl().'"', false);
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

    public function test_account_dashboard_hides_loyalty_summary_when_loyalty_feature_is_disabled(): void
    {
        app(SystemSettingsService::class)->put('user_loyalty_enabled', false);

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $desktopResponse = $this->actingAs($user)->get('/account');

        $desktopResponse
            ->assertOk()
            ->assertSee(__('ui.account.dashboard.subtitle_without_loyalty'))
            ->assertSee('class="grid gap-5 md:grid-cols-2"', false)
            ->assertDontSee('id="loyalty"', false)
            ->assertDontSee(__('ui.account.dashboard.cards.loyalty_disabled'));

        $mobileResponse = $this
            ->actingAs($user)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            ])
            ->get('/account');

        $mobileResponse
            ->assertOk()
            ->assertDontSee(__('ui.account.nav.loyalty'))
            ->assertDontSee(__('ui.account.dashboard.cards.disabled'));
    }

    public function test_checkout_creates_order_with_pretty_checkout_routes(): void
    {
        [$category] = $this->seedCategory();
        [$product] = $this->seedProduct($category->id);

        User::factory()->create([
            'email' => 'jane@example.test',
        ]);

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
            // Password managers may submit hidden registration fields even when
            // the guest did not opt into account registration.
            'register_password' => 'short',
            'register_password_confirmation' => 'different',
            'accept_terms' => '1',
        ]);

        $order = \App\Models\Sales\Order\Order::query()->first();

        $this->assertNotNull($order);

        $response->assertRedirect(route('checkout.success', ['orderNumber' => $order->order_number]));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_email' => 'jane@example.test',
            'item_qty' => 2,
            'user_id' => null,
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

    public function test_category_shows_only_option_filters_available_in_that_category_scope(): void
    {
        [$categoryA, $slugA] = $this->seedCategory();
        [$categoryB, $slugB] = $this->seedCategory();
        [$productA] = $this->seedProduct($categoryA->id);
        [$productB] = $this->seedProduct($categoryB->id);

        $user = User::factory()->create();

        $colorOption = Option::query()->create([
            'code' => 'color-scope',
            'type' => Option::TYPE_SELECT,
            'is_active' => true,
            'sort_order' => 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $colorOption->translations()->create([
            'locale' => 'en',
            'name' => 'Color Scope',
            'slug' => 'color-scope',
        ]);

        $sizeOption = Option::query()->create([
            'code' => 'size-scope',
            'type' => Option::TYPE_SELECT,
            'is_active' => true,
            'sort_order' => 2,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $sizeOption->translations()->create([
            'locale' => 'en',
            'name' => 'Size Scope',
            'slug' => 'size-scope',
        ]);

        $black = OptionValue::query()->create([
            'option_id' => $colorOption->id,
            'code' => 'black-scope',
            'is_active' => true,
            'sort_order' => 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $black->translations()->create([
            'locale' => 'en',
            'name' => 'Black Scope',
            'slug' => 'black-scope',
        ]);

        $medium = OptionValue::query()->create([
            'option_id' => $sizeOption->id,
            'code' => 'm-scope',
            'is_active' => true,
            'sort_order' => 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $medium->translations()->create([
            'locale' => 'en',
            'name' => 'M Scope',
            'slug' => 'm-scope',
        ]);

        ProductOptionValue::query()->create([
            'product_id' => $productA->id,
            'option_value_id' => $black->id,
            'parent_option_value_id' => null,
            'mode' => 'single',
            'combination_hash' => hash('sha256', 's:'.$black->id),
            'sku' => 'A-BLACK',
            'stock_qty' => 10,
            'is_active' => true,
            'sort_order' => 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        ProductOptionValue::query()->create([
            'product_id' => $productB->id,
            'option_value_id' => $medium->id,
            'parent_option_value_id' => null,
            'mode' => 'single',
            'combination_hash' => hash('sha256', 's:'.$medium->id),
            'sku' => 'B-M',
            'stock_qty' => 10,
            'is_active' => true,
            'sort_order' => 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        app(SystemSettingsService::class)->put('store_product_filter_option_ids', [$colorOption->id, $sizeOption->id]);

        $this->get('/category/'.$slugA)
            ->assertOk()
            ->assertSee('Color Scope')
            ->assertDontSee('Size Scope');

        $this->get('/category/'.$slugB)
            ->assertOk()
            ->assertSee('Size Scope')
            ->assertDontSee('Color Scope');
    }

    public function test_filter_only_option_is_hidden_on_product_page_but_stays_available_in_category_filter(): void
    {
        $this->useEnglishStorefrontLocale();
        [$category, $categorySlug] = $this->seedCategory();
        [$redProduct, $redSlug] = $this->seedProduct($category->id);
        [$blueProduct, $blueSlug] = $this->seedProduct($category->id);

        $this->attachProductSizeOptions($redProduct, ['M']);
        $this->attachProductSizeOptions($blueProduct, ['M']);

        $colorOption = $this->createProductOption('Color', false);
        $redValue = $this->attachOptionValueToProduct($redProduct, $colorOption, 'Red', 1);
        $this->attachOptionValueToProduct($blueProduct, $colorOption, 'Blue', 1);

        app(SystemSettingsService::class)->put('store_product_filter_option_ids', [$colorOption->id]);

        $this->get('/product/'.$redSlug)
            ->assertOk()
            ->assertSee('M')
            ->assertDontSee('data-size-label="Red"', false)
            ->assertDontSee('data-size-label="Blue"', false);

        $this->get('/category/'.$categorySlug)
            ->assertOk()
            ->assertSee('Color')
            ->assertSee('data-filter-kind="color"', false)
            ->assertSee('data-filter-count="1"', false)
            ->assertSee('Red')
            ->assertSee('Blue');

        $this->get('/category/'.$categorySlug.'?opt_'.$colorOption->id.'='.$redValue->id)
            ->assertOk()
            ->assertSee($redSlug)
            ->assertDontSee($blueSlug);
    }

    public function test_category_default_sort_groups_products_by_color_order(): void
    {
        $this->useEnglishStorefrontLocale();
        [$category, $categorySlug] = $this->seedCategory();
        [$redProduct, $redSlug] = $this->seedProduct($category->id);
        [$whiteProduct, $whiteSlug] = $this->seedProduct($category->id);
        [$blackProduct, $blackSlug] = $this->seedProduct($category->id);

        $colorOption = $this->createProductOption('Color', false);
        $this->attachOptionValueToProduct($redProduct, $colorOption, 'Red', 1);
        $this->attachOptionValueToProduct($whiteProduct, $colorOption, 'White', 2);
        $this->attachOptionValueToProduct($blackProduct, $colorOption, 'Black', 3);

        app(SystemSettingsService::class)->put('store_product_filter_option_ids', [$colorOption->id]);

        $this->get('/category/'.$categorySlug)
            ->assertOk()
            ->assertSee('Default order')
            ->assertSeeInOrder([
                '/product/'.$whiteSlug,
                '/product/'.$redSlug,
                '/product/'.$blackSlug,
            ], false)
            ->assertSeeInOrder([
                'White',
                'Red',
                'Black',
            ]);

        $this->get('/category/'.$categorySlug.'?sort=newest')
            ->assertOk()
            ->assertSeeInOrder([
                '/product/'.$blackSlug,
                '/product/'.$whiteSlug,
                '/product/'.$redSlug,
            ], false);
    }

    public function test_category_color_filter_uses_uploaded_swatch_image_when_available(): void
    {
        $this->useEnglishStorefrontLocale();
        [$category, $categorySlug] = $this->seedCategory();
        [$product] = $this->seedProduct($category->id);

        $colorOption = $this->createProductOption('Color', false);
        $redValue = $this->attachOptionValueToProduct($product, $colorOption, 'Red', 1);
        $redValue->update([
            'payload' => [
                'swatch_image_path' => 'catalog/option-values/swatch/red-swatch.png',
            ],
        ]);

        app(SystemSettingsService::class)->put('store_product_filter_option_ids', [$colorOption->id]);

        $this->get('/category/'.$categorySlug)
            ->assertOk()
            ->assertSee('data-filter-kind="color"', false)
            ->assertSee('data-filter-swatch=', false)
            ->assertSee('red-swatch.png', false);
    }

    public function test_product_detail_uses_filter_color_swatches_for_color_variant_links(): void
    {
        $this->useEnglishStorefrontLocale();
        [$category] = $this->seedCategory();
        [$blackProduct, $blackSlug] = $this->seedProduct($category->id);
        [$redProduct, $redSlug] = $this->seedProduct($category->id);

        $blackProduct->update(['payload' => ['source' => ['mpn' => 'BRZ-1']]]);
        $redProduct->update(['payload' => ['source' => ['mpn' => 'BRZ-1']]]);
        $this->attachProductSizeOptions($blackProduct, ['M']);
        $this->attachProductSizeOptions($redProduct, ['M']);

        $colorOption = $this->createProductOption('Color', false);
        $this->attachOptionValueToProduct($blackProduct, $colorOption, 'Black', 1);
        $redValue = $this->attachOptionValueToProduct($redProduct, $colorOption, 'Red', 2);
        $redValue->update([
            'payload' => [
                'swatch_image_path' => 'catalog/option-values/swatch/red-swatch.png',
            ],
        ]);

        $this->get('/product/'.$blackSlug)
            ->assertOk()
            ->assertSee('data-product-color-variants', false)
            ->assertSee('Color variants')
            ->assertSee('/product/'.$redSlug, false)
            ->assertSee('data-color-variant-label="Black"', false)
            ->assertSee('data-color-variant-label="Red"', false)
            ->assertSee('red-swatch.png', false)
            ->assertSee('aria-current="true"', false)
            ->assertDontSee('data-size-label="Red"', false);
    }

    public function test_product_detail_links_matching_color_variants_when_only_one_has_a_variant_group(): void
    {
        $this->useEnglishStorefrontLocale();
        [$category] = $this->seedCategory();
        [$whiteProduct, $whiteSlug] = $this->seedProduct($category->id);
        [$navyProduct, $navySlug] = $this->seedProduct($category->id);

        $whiteProduct->update(['payload' => ['source' => ['mpn' => 'WOMEN-BIKINI']]]);
        $whiteProduct->translations()->update([
            'name' => 'Women Bikini Briefs - White',
            'description' => 'Soft cotton bikini briefs.',
        ]);
        $navyProduct->translations()->update([
            'name' => 'W7042 WOMEN BIKINI BRIEFS',
            'description' => 'Soft cotton bikini briefs.',
        ]);

        $this->attachProductSizeOptions($whiteProduct, ['M']);
        $this->attachProductSizeOptions($navyProduct, ['M']);

        $colorOption = $this->createProductOption('Color', false);
        $this->attachOptionValueToProduct($whiteProduct, $colorOption, 'White', 1);
        $this->attachOptionValueToProduct($navyProduct, $colorOption, 'Navy Blue', 2);

        $this->get('/product/'.$whiteSlug)
            ->assertOk()
            ->assertSee('data-product-color-variants', false)
            ->assertSee('/product/'.$navySlug, false);

        $this->get('/product/'.$navySlug)
            ->assertOk()
            ->assertSee('data-product-color-variants', false)
            ->assertSee('/product/'.$whiteSlug, false);
    }

    public function test_filter_only_option_does_not_require_selection_on_add_to_cart(): void
    {
        [$category] = $this->seedCategory();
        [$product] = $this->seedProduct($category->id);

        $colorOption = $this->createProductOption('Color', false);
        $this->attachOptionValueToProduct($product, $colorOption, 'Red', 1);

        $this->post('/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertRedirect();

        $this->assertSame(1, app(\App\Services\Front\CartService::class)->summary()['item_qty']);
    }

    public function test_product_detail_hides_out_of_stock_option_values(): void
    {
        $this->useEnglishStorefrontLocale();
        [$category] = $this->seedCategory();
        [$product, $slug] = $this->seedProduct($category->id);

        $this->attachProductSizeOptions($product, ['S', 'L'], [
            'S' => 4,
            'L' => 0,
        ]);
        $product->update(['stock_qty' => 4]);

        $this->get('/product/'.$slug)
            ->assertOk()
            ->assertSee('data-size-label="S"', false)
            ->assertDontSee('data-size-label="L"', false)
            ->assertSee('color: #ffffff !important;', false);
    }

    public function test_product_detail_exposes_option_price_overrides_for_live_price_updates(): void
    {
        $this->useEnglishStorefrontLocale();
        [$category] = $this->seedCategory();
        [$product, $slug] = $this->seedProduct($category->id);

        $this->attachProductSizeOptions($product, ['S', 'XXL']);

        ProductOptionValue::query()
            ->where('product_id', $product->id)
            ->whereHas('optionValue.translations', fn ($query) => $query->where('name', 'XXL'))
            ->firstOrFail()
            ->update(['price_override' => 79.99]);

        $this->get('/product/'.$slug)
            ->assertOk()
            ->assertSee('data-product-default-price-current="49.99 €"', false)
            ->assertSee('data-option-price-current="79.99 €"', false)
            ->assertSee('data-option-price-current-value="79.99"', false);
    }

    public function test_shop_card_hides_out_of_stock_option_values(): void
    {
        $this->useEnglishStorefrontLocale();
        [$category] = $this->seedCategory();
        [$product] = $this->seedProduct($category->id);

        $this->attachProductSizeOptions($product, ['S', 'L'], [
            'S' => 2,
            'L' => 0,
        ]);
        $product->update(['stock_qty' => 2]);

        $this->get('/shop')
            ->assertOk()
            ->assertSee('data-option-label="S"', false)
            ->assertDontSee('data-option-label="L"', false);
    }

    public function test_product_detail_shows_unavailable_when_all_option_values_are_out_of_stock(): void
    {
        $this->useEnglishStorefrontLocale();
        [$category] = $this->seedCategory();
        [$product, $slug] = $this->seedProduct($category->id);

        $this->attachProductSizeOptions($product, ['S', 'M'], [
            'S' => 0,
            'M' => 0,
        ]);
        $product->update(['stock_qty' => 0]);

        $this->get('/product/'.$slug)
            ->assertOk()
            ->assertSee('Unavailable')
            ->assertSee('data-size-label="S"', false)
            ->assertSee('data-size-label="M"', false);
    }

    public function test_add_to_cart_returns_unavailable_when_all_option_values_are_out_of_stock(): void
    {
        $this->useEnglishStorefrontLocale();
        [$category] = $this->seedCategory();
        [$product] = $this->seedProduct($category->id);

        $this->attachProductSizeOptions($product, ['S', 'M'], [
            'S' => 0,
            'M' => 0,
        ]);
        $product->update(['stock_qty' => 0]);

        $this->postJson('/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Product option is unavailable or out of stock.');
    }

    public function test_product_detail_renders_attribute_panels_in_requested_order_without_store_check_button(): void
    {
        $this->useEnglishStorefrontLocale();
        [$category] = $this->seedCategory();
        [$product, $slug] = $this->seedProduct($category->id);

        $this->attachProductAttribute($product, 'garancija', 'Guarantee Label', '2 year guarantee', 30);
        $this->attachProductAttribute($product, 'sastav', 'Composition Label', '100% cotton', 10);
        $this->attachProductAttribute($product, 'kvaliteta', 'Quality Label', 'Premium stitching', 20);
        $this->attachProductAttribute($product, 'origin', 'Origin Label', 'Croatia', 40);

        $this->get('/product/'.$slug)
            ->assertOk()
            ->assertDontSee('Check store availability')
            ->assertSeeInOrder(['Composition Label', 'Quality Label', 'Guarantee Label'])
            ->assertSee('100% cotton')
            ->assertSee('Premium stitching')
            ->assertSee('2 year guarantee')
            ->assertDontSee('Origin Label')
            ->assertDontSee('Croatia');
    }

    public function test_product_detail_renders_material_feature_labels_under_icons(): void
    {
        $this->useEnglishStorefrontLocale();
        [$category] = $this->seedCategory();
        [$product, $slug] = $this->seedProduct($category->id);

        $this->attachProductAttribute($product, 'sastav', 'Composition Label', '95% Micromodal, 5% Elastane', 10);

        $this->get('/product/'.$slug)
            ->assertOk()
            ->assertSee('assets/payments/SVILENKASTI_DODIR.svg', false)
            ->assertSee('assets/payments/ELASTICNOST.svg', false)
            ->assertSee('assets/payments/HIPOALERGEN.svg', false)
            ->assertSeeInOrder(['95% Micromodal, 5% Elastane', 'Svilenkast', 'Elastičan', 'Hipoalergen']);
    }

    public function test_mobile_product_detail_renders_attribute_panels_in_requested_order(): void
    {
        $this->useEnglishStorefrontLocale();
        [$category] = $this->seedCategory();
        [$product, $slug] = $this->seedProduct($category->id);

        $this->attachProductAttribute($product, 'kvaliteta', 'Quality Label', 'Premium finish', 20);
        $this->attachProductAttribute($product, 'sastav', 'Composition Label', '95% cotton / 5% elastane', 10);
        $this->attachProductAttribute($product, 'garancija', 'Guarantee Label', 'Quality guarantee included', 30);

        $this
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            ])
            ->get('/product/'.$slug)
            ->assertOk()
            ->assertSeeInOrder(['Composition Label', 'Quality Label', 'Guarantee Label'])
            ->assertSee('95% cotton / 5% elastane')
            ->assertSee('Premium finish')
            ->assertSee('Quality guarantee included');
    }

    public function test_product_review_summary_links_render_on_detail_and_shop_cards(): void
    {
        $this->useEnglishStorefrontLocale();
        [$category] = $this->seedCategory();
        [$product, $slug] = $this->seedProduct($category->id);

        Comment::query()->create([
            'commentable_type' => Product::class,
            'commentable_id' => $product->id,
            'author_name' => 'Anna',
            'author_email' => 'anna@example.test',
            'locale' => 'en',
            'body' => 'Great fit and fabric.',
            'rating' => 5,
            'status' => Comment::STATUS_APPROVED,
            'is_featured' => true,
        ]);

        Comment::query()->create([
            'commentable_type' => Product::class,
            'commentable_id' => $product->id,
            'author_name' => 'Mia',
            'author_email' => 'mia@example.test',
            'locale' => 'en',
            'body' => 'Very comfortable.',
            'rating' => 4,
            'status' => Comment::STATUS_APPROVED,
            'is_featured' => false,
        ]);

        $this->get('/product/'.$slug)
            ->assertOk()
            ->assertSee('href="#product-comments"', false)
            ->assertSee('2 reviews');

        $this->get('/shop')
            ->assertOk()
            ->assertSee('/product/'.$slug.'#product-comments', false)
            ->assertSee('2 reviews');
    }

    public function test_desktop_product_detail_uses_size_guide_man_for_male_products_with_options(): void
    {
        $this->useEnglishStorefrontLocale();
        [$category] = $this->seedMaleCategory();
        [$product, $slug] = $this->seedProduct($category->id);

        $this->attachProductSizeOptions($product, ['M', 'L']);
        $this->seedSizeGuidePage('size-guide-man', 'Men size guide content');

        $this->get('/product/'.$slug)
            ->assertOk()
            ->assertSee('data-size-guide-open', false)
            ->assertSee('Men size guide content');
    }

    public function test_mobile_product_detail_renders_size_guide_when_options_exist(): void
    {
        $this->useEnglishStorefrontLocale();
        [$category] = $this->seedCategory();
        [$product, $slug] = $this->seedProduct($category->id);

        $this->attachProductSizeOptions($product, ['S', 'M']);
        $this->seedSizeGuidePage('size-guide-women', 'Women size guide content');

        $this
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            ])
            ->get('/product/'.$slug)
            ->assertOk()
            ->assertSee('data-size-guide-open', false)
            ->assertSee('Women size guide content');
    }

    public function test_product_detail_hides_size_guide_when_product_has_no_options(): void
    {
        $this->useEnglishStorefrontLocale();
        [$category] = $this->seedCategory();
        [$product, $slug] = $this->seedProduct($category->id);

        $this->seedSizeGuidePage('size-guide-women', 'Women size guide content');

        $this->get('/product/'.$slug)
            ->assertOk()
            ->assertDontSee('data-size-guide-open', false)
            ->assertDontSee('Women size guide content');
    }

    public function test_category_can_hide_filters_and_products_via_category_settings(): void
    {
        $this->useEnglishStorefrontLocale();
        [$category, $categorySlug] = $this->seedCategory();
        [$product, $productSlug] = $this->seedProduct($category->id);

        $category->update([
            'payload' => [
                Category::PAYLOAD_SHOW_FILTERS => false,
                Category::PAYLOAD_SHOW_PRODUCTS => false,
            ],
        ]);

        $response = $this->get('/category/'.$categorySlug);

        $response
            ->assertOk()
            ->assertDontSee('aria-controls="category-mobile-filter-panel"', false)
            ->assertDontSee('data-desktop-filter-form', false)
            ->assertDontSee('data-catalog-grid', false)
            ->assertDontSee('Product '.$productSlug)
            ->assertDontSee((string) $product->sku);
    }

    public function test_category_page_price_filter_filters_products(): void
    {
        $this->useEnglishStorefrontLocale();
        [$category, $categorySlug] = $this->seedCategory();
        [$cheapProduct, $cheapSlug] = $this->seedProduct($category->id);
        [$expensiveProduct, $expensiveSlug] = $this->seedProduct($category->id);

        $cheapProduct->update([
            'base_price' => 19.99,
        ]);

        $expensiveProduct->update([
            'base_price' => 89.99,
        ]);

        $this->get('/category/'.$categorySlug.'?price_min=50')
            ->assertOk()
            ->assertSee('data-price-filter-root', false)
            ->assertDontSee($cheapSlug)
            ->assertSee($expensiveSlug);
    }

    public function test_category_page_promo_only_filter_shows_only_products_with_active_sale_action(): void
    {
        $this->useEnglishStorefrontLocale();
        [$category, $categorySlug] = $this->seedCategory();
        [$regularProduct, $regularSlug] = $this->seedProduct($category->id);
        [$promoProduct, $promoSlug] = $this->seedProduct($category->id);

        CatalogAction::query()->create([
            'code' => 'promo-'.strtolower((string) str()->random(6)),
            'scope' => CatalogAction::SCOPE_PRODUCT,
            'type' => CatalogAction::TYPE_PERCENTAGE,
            'discount_value' => 20,
            'target_type' => CatalogAction::TARGET_PRODUCT,
            'audience_type' => CatalogAction::AUDIENCE_ALL,
            'is_active' => true,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ])->targets()->create([
            'target_type' => CatalogAction::TARGET_PRODUCT,
            'target_id' => $promoProduct->id,
            'sort_order' => 0,
        ]);

        $this->get('/category/'.$categorySlug.'?promo_only=1')
            ->assertOk()
            ->assertSee('data-price-range-root', false)
            ->assertDontSee($regularSlug)
            ->assertSee($promoSlug);
    }

    public function test_category_page_disables_promo_toggle_when_no_promotional_products_are_available(): void
    {
        $this->useEnglishStorefrontLocale();
        [$category, $categorySlug] = $this->seedCategory();
        [$product] = $this->seedProduct($category->id);

        $this->assertNotNull($product);

        $this->get('/category/'.$categorySlug)
            ->assertOk()
            ->assertSeeInOrder([
                'name="promo_only"',
                'disabled',
                'data-price-range-promo',
            ], false);
    }

    public function test_category_editorial_tiles_block_renders_on_targeted_category(): void
    {
        config([
            'app.locale' => 'hr',
            'app.fallback_locale' => 'hr',
        ]);
        app()->setLocale('en');
        $this->withoutMiddleware(\App\Http\Middleware\SetFrontendLocale::class);

        [$category, $categorySlug] = $this->seedCategory();

        CategoryTranslation::query()->create([
            'category_id' => $category->id,
            'scope' => Category::SCOPE_CATALOG,
            'locale' => 'hr',
            'name' => 'Kategorija za editorijal',
            'slug' => 'hr-'.$categorySlug,
            'description' => 'Opis kategorije',
        ]);

        CategoryTranslation::query()->create([
            'category_id' => $category->id,
            'scope' => Category::SCOPE_CATALOG,
            'locale' => 'de',
            'name' => 'Editorial-Kategorie',
            'slug' => 'de-'.$categorySlug,
            'description' => 'Kategoriebeschreibung',
        ]);

        $block = ContentBlock::query()->create([
            'code' => 'category-editorial-test',
            'name' => 'Category Editorial Test',
            'type' => 'category_editorial_tiles',
            'is_active' => true,
            'payload' => null,
        ]);

        $block->translations()->create([
            'locale' => 'hr',
            'title' => null,
            'subtitle' => null,
            'cta_label' => null,
            'cta_url' => null,
            'payload' => null,
        ]);

        $block->slots()->create([
            'placement' => 'category.top',
            'frontend_variant' => 'all',
            'target_type' => 'category',
            'target_ref' => 'hr-'.$categorySlug,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $image = UploadedFile::fake()->image('tile-1.jpg', 900, 1200);

        $media = $block
            ->addMedia($image->getPathname())
            ->usingName('tile-1')
            ->usingFileName('tile-1.jpg')
            ->withCustomProperties([
                'block_title' => ['hr' => 'Nightwear'],
                'link_url' => ['hr' => '/category/nightwear'],
                'alt' => ['hr' => 'Nightwear'],
            ])
            ->toMediaCollection('block_slides');

        $this->assertNotNull($media);

        foreach ([
            'hr' => 'hr-'.$categorySlug,
            'en' => $categorySlug,
            'de' => 'de-'.$categorySlug,
        ] as $locale => $localizedSlug) {
            app()->setLocale($locale);

            $this->get('/category/'.$localizedSlug)
                ->assertOk()
                ->assertSee('lang="'.$locale.'"', false)
                ->assertSee('Nightwear')
                ->assertSee('/category/nightwear', false);
        }
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
     * @return array{Category,string}
     */
    private function seedMaleCategory(): array
    {
        $category = Category::query()->create([
            'scope' => Category::SCOPE_CATALOG,
            'code' => 'men-'.strtolower((string) str()->random(6)),
            'is_active' => true,
            'show_in_menu' => true,
            'sort_order' => 1,
        ]);

        $slug = 'men-category-'.strtolower((string) str()->random(6));

        CategoryTranslation::query()->create([
            'category_id' => $category->id,
            'scope' => Category::SCOPE_CATALOG,
            'locale' => 'en',
            'name' => 'Men category',
            'slug' => $slug,
            'description' => 'Men category description',
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

    private function attachProductAttribute(Product $product, string $groupCode, string $groupName, string $name, int $sortOrder): void
    {
        $attribute = Attribute::query()->create([
            'code' => $groupCode.'-'.strtolower((string) str()->random(6)),
            'group_code' => $groupCode,
            'type' => Attribute::TYPE_SELECT,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]);

        $attribute->translations()->create([
            'locale' => 'en',
            'group_name' => $groupName,
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'description' => null,
            'payload' => null,
        ]);

        $product->attributes()->attach($attribute->id, [
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<int, string>  $labels
     */
    private function attachProductSizeOptions(Product $product, array $labels, array $stockByLabel = []): void
    {
        $option = Option::query()->create([
            'code' => 'size-'.strtolower((string) str()->random(6)),
            'type' => Option::TYPE_SELECT,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $option->translations()->create([
            'locale' => 'en',
            'name' => 'Size',
            'slug' => 'size-'.strtolower((string) str()->random(6)),
            'description' => null,
            'payload' => null,
        ]);

        $option->products()->attach($product->id, [
            'is_required' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (array_values($labels) as $index => $label) {
            $value = OptionValue::query()->create([
                'option_id' => $option->id,
                'code' => 'size-'.strtolower((string) str($label)->slug()->value()).'-'.strtolower((string) str()->random(4)),
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);

            $value->translations()->create([
                'locale' => 'en',
                'name' => $label,
                'slug' => str($label)->slug()->value().'-'.strtolower((string) str()->random(4)),
                'payload' => null,
            ]);

            ProductOptionValue::query()->create([
                'product_id' => $product->id,
                'option_value_id' => $value->id,
                'parent_option_value_id' => null,
                'mode' => 'single',
                'sku' => 'OPT-'.strtoupper((string) str()->random(4)),
                'stock_qty' => max(0, (int) ($stockByLabel[$label] ?? 5)),
                'price_override' => null,
                'sort_order' => $index + 1,
                'is_active' => true,
                'combination_hash' => hash('sha256', $product->id.'-'.$value->id.'-single'),
                'payload' => null,
            ]);
        }
    }

    private function createProductOption(string $name, bool $showOnProductPage = true): Option
    {
        $option = Option::query()->create([
            'code' => str($name)->slug()->value().'-'.strtolower((string) str()->random(6)),
            'type' => Option::TYPE_SELECT,
            'is_active' => true,
            'sort_order' => 1,
            'payload' => [
                Option::PAYLOAD_SHOW_ON_PRODUCT_PAGE => $showOnProductPage,
            ],
        ]);

        $option->translations()->create([
            'locale' => 'en',
            'name' => $name,
            'slug' => str($name)->slug()->value().'-'.strtolower((string) str()->random(6)),
            'description' => null,
            'payload' => null,
        ]);

        return $option;
    }

    private function attachOptionValueToProduct(Product $product, Option $option, string $label, int $sortOrder = 1): OptionValue
    {
        $option->products()->syncWithoutDetaching([
            $product->id => [
                'is_required' => true,
                'sort_order' => $sortOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $value = OptionValue::query()->create([
            'option_id' => $option->id,
            'code' => str($option->code.'-'.$label)->slug()->value().'-'.strtolower((string) str()->random(4)),
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]);

        $value->translations()->create([
            'locale' => 'en',
            'name' => $label,
            'slug' => str($label)->slug()->value().'-'.strtolower((string) str()->random(4)),
            'payload' => null,
        ]);

        ProductOptionValue::query()->create([
            'product_id' => $product->id,
            'option_value_id' => $value->id,
            'parent_option_value_id' => null,
            'mode' => 'single',
            'sku' => 'OPT-'.strtoupper((string) str()->random(4)),
            'stock_qty' => 5,
            'price_override' => null,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'combination_hash' => hash('sha256', $product->id.'-'.$value->id.'-single'),
            'payload' => null,
        ]);

        return $value;
    }

    private function seedSizeGuidePage(string $code, string $bodyHtml): void
    {
        $page = InfoPage::query()->create([
            'code' => $code,
            'layout' => 'default',
            'is_active' => true,
            'published_at' => now()->subDay(),
            'sort_order' => 1,
        ]);

        InfoPageTranslation::query()->create([
            'page_id' => $page->id,
            'locale' => 'en',
            'title' => 'Size guide',
            'slug' => $code,
            'excerpt' => null,
            'body_html' => '<p>'.$bodyHtml.'</p>',
        ]);
    }

    private function useEnglishStorefrontLocale(): void
    {
        config([
            'app.locale' => 'en',
            'app.fallback_locale' => 'en',
        ]);

        app()->setLocale('en');
    }

    private function createNewsletterSignupsTable(): void
    {
        if (Schema::hasTable('newsletter_signups')) {
            return;
        }

        Schema::create('newsletter_signups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->unique();
            $table->string('source', 50)->default('footer');
            $table->string('locale', 12)->default('hr');
            $table->string('provider', 20)->default('none')->index();
            $table->string('sync_status', 20)->default('skipped')->index();
            $table->boolean('consent_accepted')->default(false);
            $table->string('provider_reference')->nullable();
            $table->text('provider_error')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('subscribed_at')->nullable()->index();
            $table->timestamp('synced_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
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
