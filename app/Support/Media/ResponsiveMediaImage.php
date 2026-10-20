<?php

namespace App\Support\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ResponsiveMediaImage
{
    /**
     * @return array{src: string, srcset: string, width: int, height: int}
     */
    public static function resolve(Media $media, bool $preferWebp = false, int $fallbackHeight = 900): array
    {
        $candidates = [];
        $fallbackUrls = [];

        foreach ([540, 720, 800, 960, 1200, 1920, 2560] as $presetWidth) {
            $conversion = 'hero_'.$presetWidth.'w';
            $url = MediaUrl::conversionOrNull($media, $conversion, $preferWebp);
            if ($url === null) {
                continue;
            }
            $fallbackUrls[$presetWidth] = $url;
            $resolvedConversion = $preferWebp && $url === $media->getUrl($conversion.'_webp')
                ? $conversion.'_webp'
                : $conversion;
            $dimensions = self::dimensions($media->getPath($resolvedConversion));
            if ($dimensions !== null && $dimensions['width'] <= 2560) {
                // Fit::Max never enlarges a source: a "1920w" file may only be 1350px.
                $candidates[$dimensions['width']] = ['url' => $url] + $dimensions;
            }
        }

        $originalDimensions = self::dimensions($media->getPath());
        $hasManualCrop = (bool) data_get($media->custom_properties, 'image_edit.crop_box.enabled', false)
            || (bool) data_get($media->custom_properties, 'image_edit.crop.enabled', false);
        if (! $hasManualCrop && $originalDimensions !== null && max($originalDimensions) <= 2560) {
            // Preserve fine fabric detail at the highest available density without another lossy encode.
            $candidates[$originalDimensions['width']] = ['url' => $media->getUrl()] + $originalDimensions;
        }

        ksort($candidates, SORT_NUMERIC);
        $largest = $candidates === [] ? null : $candidates[array_key_last($candidates)];
        $fallbackUrl = $fallbackUrls[1920] ?? $fallbackUrls[1200] ?? $fallbackUrls[960]
            ?? $fallbackUrls[800] ?? $fallbackUrls[720] ?? $fallbackUrls[540] ?? $media->getUrl();

        return [
            'src' => (string) ($largest['url'] ?? $fallbackUrl),
            'srcset' => implode(', ', array_map(
                static fn (array $candidate): string => $candidate['url'].' '.$candidate['width'].'w',
                $candidates
            )),
            'width' => (int) ($largest['width'] ?? max(1, (int) ($media->width ?? 1200))),
            'height' => (int) ($largest['height'] ?? max(1, (int) ($media->height ?? $fallbackHeight))),
        ];
    }

    /**
     * @return array{width: int, height: int}|null
     */
    private static function dimensions(string $file): ?array
    {
        if (! is_file($file)) {
            return null;
        }
        $size = @getimagesize($file);

        return is_array($size) && $size[0] > 0 && $size[1] > 0
            ? ['width' => (int) $size[0], 'height' => (int) $size[1]]
            : null;
    }
}
