<?php

namespace Tests\Feature\Content;

use App\Models\Content\ContentBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class HeroBannerLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['media-library.disk_name' => 'public', 'media_profiles.presets' => []]);
    }

    #[DataProvider('heroViews')]
    public function test_hero_uses_only_the_selected_language_set_and_preserves_shared_fallback(string $view): void
    {
        $block = $this->block();
        $shared = $this->slide($block, 'shared.jpg');
        $hr = $this->slide($block, 'croatian.jpg', 'hr');
        $deFirst = $this->slide($block, 'german-first.jpg', 'de');
        $deSecond = $this->slide($block, 'german-second.jpg', 'de');

        foreach (['hr' => [$hr], 'de' => [$deFirst, $deSecond], 'en' => [$shared]] as $locale => $expected) {
            app()->setLocale($locale);
            $html = view($view, ['block' => $block->fresh(), 'translation' => null, 'storeSettings' => []])->render();
            $document = new \DOMDocument;
            @$document->loadHTML($html);
            $sources = [];
            foreach ($document->getElementsByTagName('img') as $image) {
                $sources[] = $image->getAttribute('src');
            }
            $this->assertSame(array_map(fn (Media $media): string => $media->getUrl(), $expected), $sources);
        }
    }

    public function test_other_language_slides_are_never_used_as_a_fallback(): void
    {
        $block = $this->block();
        $this->slide($block, 'german.jpg', 'de');

        $this->assertTrue($block->fresh()->slidesForLocale('hr')->isEmpty());
        $this->assertCount(1, $block->fresh()->slidesForLocale('de'));
    }

    public function test_adding_a_translated_set_does_not_prune_existing_shared_or_other_language_slides(): void
    {
        config(['media_profiles.models.'.ContentBlock::class.'.collections.block_slides.only_keep_latest' => 2]);
        $block = $this->block();
        $shared = $this->slide($block, 'shared.jpg');
        $hr = $this->slide($block, 'croatian.jpg', 'hr');
        $de = $this->slide($block, 'german.jpg', 'de');

        $this->assertSame([$shared->id, $hr->id, $de->id], $block->fresh()->getMedia('block_slides')->pluck('id')->all());
    }

    public static function heroViews(): array
    {
        return [
            'type template' => ['front.content-blocks.types.full_width_image_slider'],
            'existing instance override' => ['front.content-blocks.instances.desktopfullwidthimageslider'],
        ];
    }

    private function block(): ContentBlock
    {
        return ContentBlock::query()->create([
            'code' => 'hero-locale-test', 'name' => 'Hero',
            'type' => 'full_width_image_slider', 'is_active' => true,
        ]);
    }

    private function slide(ContentBlock $block, string $name, string $locale = ''): Media
    {
        return $block->addMedia(UploadedFile::fake()->image($name, 8, 4))
            ->withCustomProperties($locale !== '' ? ['locale' => $locale] : [])
            ->toMediaCollection('block_slides', 'public');
    }
}
