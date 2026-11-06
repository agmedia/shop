<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Media\Manager;
use App\Models\Content\ContentBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Tests\TestCase;

class HeroBannerMediaLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['media-library.disk_name' => 'public', 'media_profiles.presets' => []]);
    }

    public function test_hero_upload_uses_current_admin_language_without_changing_existing_slides(): void
    {
        $block = $this->block('full_width_image_slider');
        $shared = $block->addMedia(UploadedFile::fake()->image('existing-hr.jpg', 8, 4))->toMediaCollection('block_slides');

        $this->manager($block, 'de')
            ->assertSee('Jezik slike')
            ->set('uploads.block_slides', [UploadedFile::fake()->image('german.jpg', 8, 4)])
            ->call('uploadCollection', 'block_slides')
            ->assertHasNoErrors();

        $slides = $block->fresh()->getMedia('block_slides');
        $this->assertCount(2, $slides);
        $this->assertSame($shared->id, $slides[0]->id);
        $this->assertFalse($slides[0]->hasCustomProperty('locale'));
        $this->assertSame('de', $slides[1]->getCustomProperty('locale'));
        $this->assertSame([$slides[1]->id], $block->fresh()->slidesForLocale('de')->pluck('id')->all());
        $this->assertSame([$shared->id], $block->fresh()->slidesForLocale('hr')->pluck('id')->all());
    }

    public function test_admin_can_assign_existing_slide_to_a_language_and_restore_shared_use(): void
    {
        $block = $this->block('full_width_image_slider');
        $media = $block->addMedia(UploadedFile::fake()->image('slide.jpg', 8, 4))->toMediaCollection('block_slides');
        $manager = $this->manager($block, 'hr');

        $manager->set("meta.{$media->id}.locale", 'hr')->call('saveMeta', $media->id)->assertHasNoErrors();
        $this->assertSame('hr', $media->fresh()->getCustomProperty('locale'));

        $manager->set("meta.{$media->id}.locale", '')->call('saveMeta', $media->id)->assertHasNoErrors();
        $this->assertFalse($media->fresh()->hasCustomProperty('locale'));
    }

    public function test_non_hero_media_uploads_remain_shared(): void
    {
        $block = $this->block('dual_image_cta');
        $this->manager($block, 'de')
            ->set('uploads.block_slides', [UploadedFile::fake()->image('collection.jpg', 8, 4)])
            ->call('uploadCollection', 'block_slides')
            ->assertHasNoErrors();

        $this->assertFalse($block->fresh()->getFirstMedia('block_slides')->hasCustomProperty('locale'));
    }

    private function block(string $type): ContentBlock
    {
        return ContentBlock::query()->create([
            'code' => 'hero-admin-locale-test', 'name' => 'Hero', 'type' => $type, 'is_active' => true,
        ]);
    }

    private function manager(ContentBlock $block, string $locale)
    {
        $user = User::factory()->create();
        Bouncer::assign('admin')->to($user);

        return Livewire::actingAs($user)->test(Manager::class, [
            'modelClass' => ContentBlock::class, 'modelId' => $block->id, 'locale' => $locale,
        ]);
    }
}
