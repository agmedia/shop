<?php

namespace Tests\Unit\Localization;

use Illuminate\Support\Arr;
use Tests\TestCase;

class StorefrontTranslationParityTest extends TestCase
{
    public function test_croatian_and_english_translation_files_have_the_same_keys(): void
    {
        $croatianFiles = $this->translationFiles('hr');
        $englishFiles = $this->translationFiles('en');

        $this->assertSame($croatianFiles, $englishFiles);

        foreach ($croatianFiles as $file) {
            $this->assertSame(
                $this->translationKeys('hr', $file),
                $this->translationKeys('en', $file),
                'Translation keys differ in '.$file
            );
        }
    }

    public function test_existing_german_translation_files_match_the_croatian_fallback_shape(): void
    {
        foreach ($this->translationFiles('de') as $file) {
            $this->assertFileExists(lang_path('hr/'.$file));
            $this->assertSame(
                $this->translationKeys('hr', $file),
                $this->translationKeys('de', $file),
                'German translation keys differ in '.$file
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function translationFiles(string $locale): array
    {
        $files = array_map(
            static fn (string $path): string => basename($path),
            glob(lang_path($locale.'/*.php')) ?: []
        );

        sort($files);

        return $files;
    }

    /**
     * @return array<int, string>
     */
    private function translationKeys(string $locale, string $file): array
    {
        $translations = require lang_path($locale.'/'.$file);
        $keys = array_keys(Arr::dot($translations));

        sort($keys);

        return $keys;
    }
}
