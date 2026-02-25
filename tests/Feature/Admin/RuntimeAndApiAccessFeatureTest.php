<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

class RuntimeAndApiAccessFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_runtime_and_api_pages(): void
    {
        $admin = $this->makeUserWithRole('admin');

        $this->actingAs($admin)
            ->get('/admin/settings/system/runtime')
            ->assertOk()
            ->assertSee('Runtime Controls');

        $this->actingAs($admin)
            ->get('/admin/settings/api')
            ->assertRedirect('/admin/settings/api/wholesale');

        $this->actingAs($admin)
            ->get('/admin/settings/api/wholesale')
            ->assertOk()
            ->assertSee('Wholesale API Settings');

        $this->actingAs($admin)
            ->get('/admin/settings/api/luceed')
            ->assertOk()
            ->assertSee('Luceed API');
    }

    public function test_editor_cannot_open_runtime_and_api_pages(): void
    {
        $editor = $this->makeUserWithRole('editor');

        $this->actingAs($editor)
            ->get('/admin/settings/system/runtime')
            ->assertForbidden();

        $this->actingAs($editor)
            ->get('/admin/settings/api')
            ->assertForbidden();

        $this->actingAs($editor)
            ->get('/admin/settings/api/wholesale')
            ->assertForbidden();

        $this->actingAs($editor)
            ->get('/admin/settings/api/luceed')
            ->assertForbidden();
    }

    public function test_api_index_redirects_to_luceed_when_catalog_api_feature_is_disabled(): void
    {
        $admin = $this->makeUserWithRole('admin');

        app(SystemSettingsService::class)->put('catalog_use_api', false);

        $this->actingAs($admin)
            ->get('/admin/settings/api')
            ->assertRedirect('/admin/settings/api/luceed');

        $this->actingAs($admin)
            ->get('/admin/settings/api/wholesale')
            ->assertRedirect(route('admin.settings.system.catalog-features'));
    }

    public function test_luceed_route_is_blocked_when_luceed_feature_is_disabled(): void
    {
        $admin = $this->makeUserWithRole('admin');

        app(SystemSettingsService::class)->putMany([
            'catalog_use_api' => true,
            'catalog_use_luceed_api' => false,
        ]);

        $this->actingAs($admin)
            ->get('/admin/settings/api/luceed')
            ->assertRedirect(route('admin.settings.system.catalog-features'));
    }

    public function test_api_index_redirects_to_catalog_features_when_no_api_modules_are_enabled(): void
    {
        $admin = $this->makeUserWithRole('admin');

        app(SystemSettingsService::class)->putMany([
            'catalog_use_api' => false,
            'catalog_use_luceed_api' => false,
        ]);

        $this->actingAs($admin)
            ->get('/admin/settings/api')
            ->assertRedirect(route('admin.settings.system.catalog-features'));
    }

    private function makeUserWithRole(string $role): User
    {
        Bouncer::role()->firstOrCreate(['name' => 'superadmin']);
        Bouncer::role()->firstOrCreate(['name' => 'admin']);
        Bouncer::role()->firstOrCreate(['name' => 'editor']);
        Bouncer::role()->firstOrCreate(['name' => 'customer']);

        $user = User::factory()->create();
        Bouncer::assign($role)->to($user);

        return $user;
    }
}
