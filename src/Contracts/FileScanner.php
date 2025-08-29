<?php

declare(strict_types=1);

namespace Syriable\Localizator\Contracts;

interface FileScanner
{
    public function scanDirectories(array $directories): array;

    public function extractTranslationKeys(string $filePath): array;

    public function findTranslationFunction(string $content): array;
}
