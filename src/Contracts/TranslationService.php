<?php

declare(strict_types=1);

namespace Syriable\Localizator\Contracts;

interface TranslationService
{
    public function translate(string $text, string $sourceLanguage, string $targetLanguage): string;

    public function translateBatch(array $texts, string $sourceLanguage, string $targetLanguage): array;

    public function getSupportedLanguages(): array;

    public function validateTranslation(string $original, string $translated, string $targetLanguage): bool;
}
