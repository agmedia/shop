<?php

use App\Models\Catalog\Category\Category;
use App\Models\Catalog\Manufacturer\Manufacturer;
use App\Models\Catalog\Product\Product;
use App\Models\Content\Blog\BlogPost;
use App\Models\Content\ContentBlock;

return [
    /*
    |--------------------------------------------------------------------------
    | Conversion Presets
    |--------------------------------------------------------------------------
    |
    | Keep conversion keys explicit (thumb_100x100, card_360x240...) so generated
    | files are easy to recognize and predictable.
    |
    */
    'presets' => [
        'thumb_100x100' => [
            'fit' => 'crop',
            'width' => 100,
            'height' => 100,
            'quality' => 86,
            'format' => null, // keep original format
        ],
        'icon_96x96' => [
            'fit' => 'crop',
            'width' => 96,
            'height' => 96,
            'quality' => 86,
            'format' => null,
        ],
        'card_360x240' => [
            'fit' => 'crop',
            'width' => 360,
            'height' => 240,
            'quality' => 86,
            'format' => null,
        ],
        'detail_960x960' => [
            'fit' => 'contain',
            'width' => 960,
            'height' => 960,
            'quality' => 88,
            'format' => null,
        ],
        'hero_1440x480' => [
            'fit' => 'crop',
            'width' => 1440,
            'height' => 480,
            'quality' => 86,
            'format' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Media Profiles
    |--------------------------------------------------------------------------
    */
    'models' => [
        Product::class => [
            'label' => 'Product',
            'main_collection' => 'product_main',
            'collections' => [
                'product_main' => [
                    'label' => 'Main Image',
                    'single_file' => true,
                    'max_upload_kb' => 8192,
                    'accept_mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif'],
                    'conversions' => ['thumb_100x100', 'card_360x240', 'detail_960x960'],
                    'preview_conversion' => 'card_360x240',
                ],
                'product_gallery' => [
                    'label' => 'Gallery',
                    'single_file' => false,
                    'max_upload_kb' => 8192,
                    'accept_mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif'],
                    'conversions' => ['thumb_100x100', 'card_360x240', 'detail_960x960'],
                    'preview_conversion' => 'card_360x240',
                ],
            ],
        ],
        BlogPost::class => [
            'label' => 'Blog Post',
            'main_collection' => 'blog_cover',
            'collections' => [
                'blog_cover' => [
                    'label' => 'Cover Image',
                    'single_file' => true,
                    'max_upload_kb' => 8192,
                    'accept_mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif'],
                    'conversions' => ['thumb_100x100', 'card_360x240', 'hero_1440x480'],
                    'preview_conversion' => 'card_360x240',
                ],
                'blog_gallery' => [
                    'label' => 'Gallery',
                    'single_file' => false,
                    'max_upload_kb' => 8192,
                    'accept_mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif'],
                    'conversions' => ['thumb_100x100', 'card_360x240', 'detail_960x960'],
                    'preview_conversion' => 'card_360x240',
                ],
            ],
        ],
        Category::class => [
            'label' => 'Category',
            'collections' => [
                'category_icon' => [
                    'label' => 'Icon Image',
                    'single_file' => true,
                    'max_upload_kb' => 4096,
                    'accept_mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/svg+xml'],
                    'conversions' => ['icon_96x96', 'thumb_100x100'],
                    'preview_conversion' => 'icon_96x96',
                ],
                'category_banner' => [
                    'label' => 'Banner Image',
                    'single_file' => true,
                    'max_upload_kb' => 8192,
                    'accept_mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif'],
                    'conversions' => ['card_360x240', 'hero_1440x480'],
                    'preview_conversion' => 'card_360x240',
                ],
            ],
        ],
        Manufacturer::class => [
            'label' => 'Manufacturer',
            'collections' => [
                'manufacturer_logo' => [
                    'label' => 'Logo Image',
                    'single_file' => true,
                    'max_upload_kb' => 4096,
                    'accept_mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/svg+xml'],
                    'conversions' => ['icon_96x96', 'thumb_100x100'],
                    'preview_conversion' => 'icon_96x96',
                ],
                'manufacturer_banner' => [
                    'label' => 'Banner Image',
                    'single_file' => true,
                    'max_upload_kb' => 8192,
                    'accept_mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif'],
                    'conversions' => ['card_360x240', 'hero_1440x480'],
                    'preview_conversion' => 'card_360x240',
                ],
            ],
        ],
        ContentBlock::class => [
            'label' => 'Content Block',
            'collections' => [
                'block_background' => [
                    'label' => 'Block Background',
                    'single_file' => true,
                    'max_upload_kb' => 8192,
                    'accept_mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif'],
                    'conversions' => ['card_360x240', 'hero_1440x480'],
                    'preview_conversion' => 'card_360x240',
                ],
                'block_slides' => [
                    'label' => 'Block Slides',
                    'single_file' => false,
                    'only_keep_latest' => 30,
                    'max_upload_kb' => 12288,
                    'accept_mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif'],
                    'conversions' => ['card_360x240', 'hero_1440x480'],
                    'preview_conversion' => 'card_360x240',
                ],
            ],
        ],
    ],
];
