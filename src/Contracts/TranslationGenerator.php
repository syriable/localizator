<?php

declare(strict_types=1);

namespace Syriable\Localizator\Contracts;

interface TranslationGenerator
{
    public function generateTranslationFiles(array $translationKeys, array $locales): bool;

    public function generateJsonTranslationFile(string $locale, array $translations): bool;

    public function generatePhpTranslationFile(string $locale, array $translations): bool;

    public function mergeExistingTranslations(string $locale, array $newTranslations): array;
}
