<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Content\Navigation\Manager as NavigationManager;
use App\Models\Catalog\Category\Category;
use App\Models\Catalog\Category\CategoryTranslation;
use App\Models\Content\Page\InfoPage;
use App\Models\Content\Page\InfoPageTranslation;
use App\Models\User;
use App\Services\Front\NavigationMenuService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

class ContentNavigationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_navigation_page(): void
    {
        $user = $this->makeAdminUser();

        $this->actingAs($user)
            ->get('/admin/content/navigation')
            ->assertOk()
            ->assertSee('Main Navigation');
    }

    public function test_admin_can_save_navigation_config(): void
    {
        $user = $this->makeAdminUser();

        $category = Category::query()->create([
            'scope' => Category::SCOPE_CATALOG,
            'code' => 'food',
            'is_active' => true,
            'show_in_menu' => true,
            'sort_order' => 10,
        ]);

        CategoryTranslation::query()->create([
            'category_id' => $category->id,
            'scope' => Category::SCOPE_CATALOG,
            'locale' => 'en',
            'name' => 'Food',
            'slug' => 'food',
        ]);

        $page = InfoPage::query()->create([
            'code' => 'faq',
            'layout' => 'default',
            'is_active' => true,
            'show_in_footer' => false,
            'sort_order' => 20,
        ]);

        InfoPageTranslation::query()->create([
            'page_id' => $page->id,
            'locale' => 'en',
            'title' => 'FAQ',
            'slug' => 'faq',
        ]);

        Livewire::actingAs($user)
            ->test(NavigationManager::class)
            ->set('form.items', [
                [
                    'type' => 'category',
                    'label' => '',
                    'category_id' => $category->id,
                    'page_id' => 0,
                    'url' => '',
                    'open_in_new_tab' => false,
                    'show_dropdown' => true,
                    'is_active' => true,
                    'sort_order' => 0,
                ],
                [
                    'type' => 'page',
                    'label' => '',
                    'category_id' => 0,
                    'page_id' => $page->id,
                    'url' => '',
                    'open_in_new_tab' => false,
                    'show_dropdown' => false,
                    'is_active' => true,
                    'sort_order' => 1,
                ],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $saved = app(SystemSettingsService::class)->get(NavigationMenuService::SETTINGS_KEY, []);

        $this->assertIsArray($saved);
        $this->assertCount(2, $saved);
        $this->assertSame('category', $saved[0]['type']);
        $this->assertSame((int) $category->id, (int) $saved[0]['category_id']);
        $this->assertSame('page', $saved[1]['type']);
        $this->assertSame((int) $page->id, (int) $saved[1]['page_id']);
    }

    private function makeAdminUser(): User
    {
        $user = User::factory()->create();

        Bouncer::role()->firstOrCreate(['name' => 'admin']);
        Bouncer::assign('admin')->to($user);

        return $user;
    }
}
