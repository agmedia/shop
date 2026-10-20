<?php

namespace Tests\Feature\Content;

use App\Models\Content\ContentBlock;
use App\Services\Settings\SystemSettingsService;
use App\Support\Media\ResponsiveMediaImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class ResponsiveBannerImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Queue::fake();
        config(['media-library.disk_name' => 'public', 'media-library.queue_conversions_by_default' => true]);
    }

    public function test_both_banner_templates_offer_original_detail_with_truthful_deduplicated_widths(): void
    {
        app(SystemSettingsService::class)->put('store_images_use_webp', true);
        [$block, $media] = $this->banner(1350, 718);
        $this->conversion($media, 'hero_540w_webp', 540, 287);
        $this->conversion($media, 'hero_1200w_webp', 1200, 638);
        $this->conversion($media, 'hero_1920w_webp', 1350, 718);
        $this->conversion($media, 'hero_2560w_webp', 1350, 718);

        $image = ResponsiveMediaImage::resolve($media, preferWebp: true);
        $expected = $media->getUrl('hero_540w_webp').' 540w, '
            .$media->getUrl('hero_1200w_webp').' 1200w, '.$media->getUrl().' 1350w';
        $this->assertSame($expected, $image['srcset']);
        $this->assertSame($media->getUrl(), $image['src']);
        $this->assertSame(1350, $image['width']);
        $this->assertSame(718, $image['height']);

        foreach (['category_editorial_tiles', 'dual_image_cta'] as $type) {
            $html = view('front.content-blocks.types.'.$type, [
                'block' => $block->fresh(), 'translation' => null,
                'storeSettings' => ['images' => ['use_webp' => true]],
            ])->render();
            $document = new \DOMDocument;
            @$document->loadHTML($html);
            $img = $document->getElementsByTagName('img')->item(0);
            $this->assertNotNull($img);
            $this->assertSame($expected, $img->getAttribute('srcset'));
            $this->assertSame('1350', $img->getAttribute('width'));
            $this->assertSame('718', $img->getAttribute('height'));
            $this->assertStringNotContainsString(' 1920w', $img->getAttribute('srcset'));
            $this->assertStringNotContainsString(' 2560w', $img->getAttribute('srcset'));
        }
    }

    public function test_manually_cropped_banner_keeps_the_cropped_conversions_at_every_density(): void
    {
        [, $media] = $this->banner(1350, 718);
        $media->setCustomProperty('image_edit.crop_box', ['enabled' => true, 'x' => 0, 'y' => 0, 'width' => 80, 'height' => 100])->save();
        $this->conversion($media, 'hero_1200w', 1080, 718);
        $this->conversion($media, 'hero_1920w', 1080, 718);

        $image = ResponsiveMediaImage::resolve($media);
        $this->assertSame($media->getUrl('hero_1920w').' 1080w', $image['srcset']);
        $this->assertSame($media->getUrl('hero_1920w'), $image['src']);
        $this->assertStringNotContainsString($media->getUrl().' ', $image['srcset']);
        $this->assertSame(1080, $image['width']);
    }

    public function test_oversized_original_is_served_through_existing_bounded_conversions(): void
    {
        [, $media] = $this->banner(3000, 1596);
        $this->conversion($media, 'hero_1200w', 1200, 638);
        $this->conversion($media, 'hero_2560w', 2560, 1362);

        $image = ResponsiveMediaImage::resolve($media);
        $this->assertSame($media->getUrl('hero_2560w'), $image['src']);
        $this->assertStringNotContainsString($media->getUrl().' ', $image['srcset']);
        $this->assertStringNotContainsString(' 3000w', $image['srcset']);
        $this->assertStringContainsString(' 2560w', $image['srcset']);
    }

    public function test_missing_files_keep_the_existing_original_url_without_inventing_srcset_widths(): void
    {
        [, $media] = $this->banner(1350, 718);
        $media->markAsConversionGenerated('hero_1200w');
        File::delete($media->getPath());

        $image = ResponsiveMediaImage::resolve($media);
        $this->assertSame($media->getUrl(), $image['src']);
        $this->assertSame('', $image['srcset']);
    }

    /**
     * @return array{ContentBlock, Media}
     */
    private function banner(int $width, int $height): array
    {
        $block = ContentBlock::query()->create([
            'code' => 'editorial-test', 'name' => 'Editorial test',
            'type' => 'category_editorial_tiles', 'is_active' => true,
        ]);
        $media = $block->addMedia(UploadedFile::fake()->image('banner.jpg', $width, $height))
            ->toMediaCollection('block_slides', 'public');

        return [$block, $media];
    }

    private function conversion(Media $media, string $name, int $width, int $height): void
    {
        $file = $media->getPath($name);
        File::ensureDirectoryExists(dirname($file));
        $image = imagecreatetruecolor($width, $height);
        if (str_ends_with($name, '_webp')) {
            imagewebp($image, $file, 84);
        } else {
            imagejpeg($image, $file, 84);
        }
        imagedestroy($image);
        $media->markAsConversionGenerated($name);
    }
}
