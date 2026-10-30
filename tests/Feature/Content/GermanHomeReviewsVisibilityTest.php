<?php

namespace Tests\Feature\Content;

use App\Models\Catalog\Product\Product;
use App\Models\Content\ContentBlock;
use App\Models\Content\Support\Comment;
use App\Services\Content\ContentBlockResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GermanHomeReviewsVisibilityTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('frontendVariants')]
    public function test_german_home_omits_the_entire_reviews_block_including_its_wrapper(string $variant): void
    {
        $this->reviewsBlock($variant);

        $html = $this->renderPlacement('de', $variant);

        $this->assertSame('', trim($html));
        $this->assertStringNotContainsString('KOMENTARI KUPACA', $html);
        $this->assertStringNotContainsString('review-wrapper', $html);
        $this->assertStringNotContainsString('data-five-star-reviews-splide', $html);
    }

    #[DataProvider('frontendVariants')]
    public function test_croatian_home_keeps_the_existing_reviews_title_and_content(string $variant): void
    {
        $this->reviewsBlock($variant);

        $html = $this->renderPlacement('hr', $variant);

        $this->assertStringContainsString('KOMENTARI KUPACA', $html);
        $this->assertStringContainsString('Postojeći hrvatski komentar.', $html);
        $this->assertStringContainsString('review-wrapper', $html);
        $this->assertStringContainsString('data-five-star-reviews-splide', $html);
    }

    public function test_english_home_keeps_its_existing_reviews_block(): void
    {
        $this->reviewsBlock('desktop');

        $html = $this->renderPlacement('en', 'desktop');

        $this->assertStringContainsString('Existing customer review.', $html);
        $this->assertStringContainsString('data-five-star-reviews-splide', $html);
    }

    public static function frontendVariants(): array
    {
        return [['desktop'], ['mobile']];
    }

    private function reviewsBlock(string $variant): void
    {
        $block = ContentBlock::query()->create([
            'code' => 'comments',
            'name' => 'Comments',
            'type' => 'five_star_reviews_carousel',
            'is_active' => true,
        ]);
        foreach (['hr', 'en'] as $locale) {
            $block->translations()->create([
                'locale' => $locale,
                'title' => 'KOMENTARI KUPACA',
                'payload' => ['custom_classes' => 'review-wrapper', 'bg_css' => 'min-height:300px'],
            ]);
        }
        $block->slots()->create([
            'placement' => 'home.after_products',
            'frontend_variant' => $variant,
            'is_active' => true,
        ]);

        $product = Product::query()->create(['code' => 'review-product', 'is_active' => true]);
        foreach ([
            'hr' => 'Postojeći hrvatski komentar.',
            'en' => 'Existing customer review.',
            'de' => 'Bereits veröffentlichte Bewertung.',
        ] as $locale => $body) {
            Comment::query()->create([
                'commentable_type' => Product::class,
                'commentable_id' => $product->id,
                'locale' => $locale,
                'body' => $body,
                'author_name' => 'Existing author',
                'status' => Comment::STATUS_APPROVED,
                'rating' => 5,
            ]);
        }
    }

    private function renderPlacement(string $locale, string $variant): string
    {
        config(['app.locale' => 'en']);
        app()->setLocale($locale);
        $items = app(ContentBlockResolver::class)->forPlacement(
            'home.after_products', $locale, frontendVariant: $variant
        );
        $this->assertCount(1, $items);

        return view('components.content-placement', ['items' => $items])->render();
    }
}
