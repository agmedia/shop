<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Catalog\Category\Form as CategoryForm;
use App\Models\Catalog\Category\Category;
use App\Models\User;
use App\Services\Front\NavigationMenuService;
use App\Services\Settings\SystemSettingsService;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

class CategoryNavigationColorFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.locale' => 'en', 'app.fallback_locale' => 'en']);
        app()->setLocale('en');
    }

    public function test_admin_can_save_reopen_and_reset_a_color_without_losing_other_payload(): void
    {
        $admin = $this->admin();
        Livewire::actingAs($admin)->test(CategoryForm::class)
            ->set('form.locale', 'en')
            ->set('form.code', 'colored-navigation')
            ->set('form.name', 'Colored navigation')
            ->set('form.slug', 'colored-navigation')
            ->set('form.navigation_color', '  #C70A29  ')
            ->set('form.catalog_show_filters', false)
            ->set('form.payload_text', json_encode(['source' => ['catalog_id' => 'keep-42'], 'custom_note' => 'preserve']))
            ->call('save')->assertHasNoErrors();

        $category = Category::query()->where('code', 'colored-navigation')->firstOrFail();
        $this->assertSame('#c70a29', $category->navigationColor());
        $this->assertSame('#c70a29', $category->payload['navigation_color']);
        $this->assertSame(['catalog_id' => 'keep-42'], $category->payload['source']);
        $this->assertSame('preserve', $category->payload['custom_note']);
        $this->assertFalse($category->payload[Category::PAYLOAD_SHOW_FILTERS]);

        Livewire::actingAs($admin)->test(CategoryForm::class, ['categoryId' => $category->id])
            ->assertSet('form.navigation_color', '#c70a29')
            ->set('form.navigation_color', '')
            ->call('save')->assertHasNoErrors();

        $category->refresh();
        $this->assertNull($category->navigationColor());
        $this->assertArrayNotHasKey('navigation_color', $category->payload);
        $this->assertSame(['catalog_id' => 'keep-42'], $category->payload['source']);
        $this->assertSame('preserve', $category->payload['custom_note']);
        $this->assertFalse($category->payload[Category::PAYLOAD_SHOW_FILTERS]);
        Livewire::actingAs($admin)->test(CategoryForm::class, ['categoryId' => $category->id])
            ->assertSet('form.navigation_color', '');
    }

    #[DataProvider('invalidColors')]
    public function test_admin_rejects_invalid_css_values_and_leaves_the_saved_category_unchanged(mixed $color): void
    {
        $category = $this->category('existing', null, ['navigation_color' => '#112233', 'keep' => true]);
        $before = $category->fresh()->getAttributes();
        Livewire::actingAs($this->admin())->test(CategoryForm::class, ['categoryId' => $category->id])
            ->set('form.navigation_color', $color)->call('save')
            ->assertHasErrors('form.navigation_color');

        $this->assertSame($before, $category->fresh()->getAttributes());
    }

    #[DataProvider('invalidColors')]
    public function test_legacy_invalid_payload_values_are_ignored_when_resolving_navigation_colors(mixed $color): void
    {
        $category = new Category(['payload' => ['navigation_color' => $color]]);
        $this->assertNull($category->navigationColor());
    }

    public function test_color_is_scoped_to_catalog_categories_when_admin_saves_a_different_scope(): void
    {
        Livewire::actingAs($this->admin())->test(CategoryForm::class)
            ->set('form.scope', Category::SCOPE_BLOG)
            ->set('form.locale', 'en')->set('form.code', 'blog-color')
            ->set('form.name', 'Blog color')->set('form.slug', 'blog-color')
            ->set('form.navigation_color', '#c70a29')
            ->set('form.payload_text', json_encode(['keep' => true, 'navigation_color' => '#112233']))
            ->call('save')->assertHasNoErrors();

        $category = Category::query()->where('code', 'blog-color')->firstOrFail();
        $this->assertSame(['keep' => true], $category->payload);
    }

    #[DataProvider('localizedNavigationViews')]
    public function test_configured_roots_and_automatic_descendants_color_only_their_own_labels(string $locale, string $view): void
    {
        $root = $this->category('root', null, ['navigation_color' => '#112233']);
        $colored = $this->category('christmas', $root, ['navigation_color' => '#c70a29']);
        $this->category('plain-descendant', $colored, ['navigation_color' => 'red; background:url(https://invalid.test)']);
        $this->category('plain-sibling', $root);
        app(SystemSettingsService::class)->put(NavigationMenuService::SETTINGS_KEY, [
            ['type' => 'category', 'category_id' => $root->id, 'is_active' => true, 'show_dropdown' => true],
            ['type' => 'custom', 'label_translations' => ['hr' => 'Custom HR', 'de' => 'Custom DE'], 'url' => '/custom', 'is_active' => true],
        ]);
        app()->setLocale($locale);
        $navigation = app(NavigationMenuService::class)->forLocale($locale);
        $this->assertSame('#112233', $navigation[0]['color']);
        $this->assertSame('#c70a29', $navigation[0]['children'][0]['color']);
        $this->assertNull($navigation[0]['children'][0]['children'][0]['color']);
        $this->assertNull($navigation[0]['children'][1]['color']);
        $this->assertNull($navigation[1]['color'] ?? null);

        $html = view($view, ['mainNavigation' => $navigation, 'storeSettings' => []])->render();
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new DOMXPath($document);
        foreach (['root' => '#112233', 'christmas' => '#c70a29', 'plain-descendant' => null, 'plain-sibling' => null] as $code => $color) {
            $label = $code.'-'.strtoupper($locale);
            $nodes = $xpath->query('//*[normalize-space(text())="'.$label.'"]');
            $this->assertGreaterThan(0, $nodes->length, 'Missing localized category label '.$label);
            foreach ($nodes as $node) {
                $this->assertSame($color, $this->inheritedInlineColor($node), $view.' unexpected color or inheritance for '.$label);
            }
        }
        $this->assertStringNotContainsString('background:url(https://invalid.test)', $html);
    }

    public static function invalidColors(): array
    {
        return [
            ['red'], ['#abc'], ['#aabbccdd'], ['#c70a29; background:url(https://invalid.test)'],
            ['var(--marketing)'], [['#c70a29']],
        ];
    }

    public static function localizedNavigationViews(): array
    {
        return [
            ['hr', 'front.desktop.partials.main-nav'], ['de', 'front.desktop.partials.main-nav'],
            ['hr', 'front.desktop.partials.main-nav-mobile'], ['de', 'front.desktop.partials.main-nav-mobile'],
            ['hr', 'front.mobile.menu-main'], ['de', 'front.mobile.menu-main'],
        ];
    }

    private function category(string $code, ?Category $parent = null, ?array $payload = null): Category
    {
        $category = new Category([
            'scope' => Category::SCOPE_CATALOG, 'code' => $code, 'is_active' => true,
            'show_in_menu' => true, 'payload' => $payload,
        ]);
        $parent ? $category->appendToNode($parent)->save() : $category->saveAsRoot();
        foreach (['en', 'hr', 'de'] as $locale) {
            $category->translations()->create([
                'scope' => Category::SCOPE_CATALOG, 'locale' => $locale,
                'name' => $code.'-'.strtoupper($locale), 'slug' => $code.'-'.$locale,
            ]);
        }

        return $category;
    }

    private function inheritedInlineColor(DOMElement $node): ?string
    {
        for ($current = $node; $current instanceof DOMElement; $current = $current->parentNode) {
            if (preg_match('/(?:^|;)\s*color\s*:\s*(#[a-f0-9]{6})(?:\s*!important)?\s*(?:;|$)/i', $current->getAttribute('style'), $match)) {
                return strtolower($match[1]);
            }
        }

        return null;
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        Bouncer::role()->firstOrCreate(['name' => 'admin']);
        Bouncer::assign('admin')->to($user);

        return $user;
    }
}
