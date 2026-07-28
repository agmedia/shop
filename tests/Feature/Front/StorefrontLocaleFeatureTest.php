<?php

namespace Tests\Feature\Front;

use App\Models\Catalog\Category\Category;
use App\Models\Catalog\Category\CategoryTranslation;
use App\Models\Settings\Local\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->get('/category/muskarci')
            ->assertOk()
            ->assertSee('Muškarci');

        $this->from('/category/muskarci')
            ->get('/locale/en')
            ->assertRedirect('/category/muskarci')
            ->assertSessionHas('front_locale', 'en');

        $this->get('/category/muskarci?sort=newest')
            ->assertRedirect('/category/men?sort=newest');

        $this->get('/category/men')
            ->assertOk()
            ->assertSee('Men');

        $this->from('/category/men')
            ->get('/locale/hr')
            ->assertRedirect('/category/men')
            ->assertSessionHas('front_locale', 'hr');

        $this->get('/category/men?sort=newest')
            ->assertRedirect('/category/muskarci?sort=newest');

        $this->get('/category/muskarci')
            ->assertOk()
            ->assertSee('Muškarci');
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
