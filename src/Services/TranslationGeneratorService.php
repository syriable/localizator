<?php

declare(strict_types=1);

namespace Syriable\Localizator\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Syriable\Localizator\Contracts\TranslationGenerator;

class TranslationGeneratorService implements TranslationGenerator
{
    private string $langPath;

    private bool $shouldSort;

    private bool $generateComments;

    private int $indent;

    private bool $useNestedStructure;

    public function __construct()
    {
        $this->langPath = $this->detectLangPath();
        // Don't cache localizationType - read it fresh each time to allow command overrides
        $this->shouldSort = Config::get('localizator.sort', true);
        $this->generateComments = Config::get('localizator.output.comments', true);
        // Don't cache backup setting - read it fresh to allow command overrides
        $this->indent = Config::get('localizator.output.indent', 4);
        $this->useNestedStructure = Config::get('localizator.nested', true);
    }

    /**
     * Get the current localization type from config (allows command overrides)
     */
    private function getLocalizationType(): string
    {
        return Config::get('localizator.localize', 'default');
    }

    /**
     * Ensure the language directory exists, creating it if necessary
     */
    private function ensureLangDirectoryExists(): void
    {
        if (File::exists($this->langPath)) {
            return;
        }

        // Directory doesn't exist, try to create it
        if ($this->supportsLangPublishCommand()) {
            // Use Laravel's lang:publish command for Laravel 9+
            $this->runLangPublishCommand();
        } else {
            // Manually create directory for older Laravel versions
            $this->createLangDirectoryManually();
        }
        
        // Verify the directory was created successfully
        if (!File::exists($this->langPath)) {
            throw new \RuntimeException(
                "Failed to create language directory at '{$this->langPath}'. " .
                "Please check directory permissions and ensure you have write access to the parent directory."
            );
        }
    }

    /**
     * Check if the current Laravel version supports the lang:publish command
     */
    private function supportsLangPublishCommand(): bool
    {
        // Laravel 9+ supports lang:publish command
        if (function_exists('app')) {
            $laravelVersion = app()->version();
            return version_compare($laravelVersion, '9.0', '>=');
        }

        return false;
    }

    /**
     * Run the lang:publish artisan command
     */
    private function runLangPublishCommand(): void
    {
        try {
            if (function_exists('app') && app()->bound('artisan')) {
                app('artisan')->call('lang:publish');
                
                // If the command succeeded but directory still doesn't exist,
                // fall back to manual creation
                if (!File::exists($this->langPath)) {
                    $this->createLangDirectoryManually();
                }
            } else {
                // Laravel environment not available, use manual creation
                $this->createLangDirectoryManually();
            }
        } catch (\Exception $e) {
            // Fall back to manual creation if command fails
            $this->createLangDirectoryManually();
        }
    }

    /**
     * Manually create the language directory
     */
    private function createLangDirectoryManually(): void
    {
        try {
            // Ensure parent directories exist first
            $parentDir = dirname($this->langPath);
            if (!File::exists($parentDir)) {
                File::makeDirectory($parentDir, 0755, true);
            }
            
            // Now create the lang directory
            File::makeDirectory($this->langPath, 0755, true);
        } catch (\Exception $e) {
            // If we can't create the directory, throw a more descriptive error
            throw new \RuntimeException(
                "Could not create language directory at '{$this->langPath}'. " .
                "Please check directory permissions and ensure the parent directory exists. " .
                "Error: {$e->getMessage()}"
            );
        }
    }

    /**
     * Detect the correct language path for the current Laravel version.
     * Laravel 9+ uses base_path('lang'), while earlier versions use resource_path('lang').
     * This method provides backward compatibility by checking which directory exists.
     */
    private function detectLangPath(): string
    {
        // Check if Laravel provides the langPath method (Laravel 9+)
        if (function_exists('app') && method_exists(app(), 'langPath')) {
            return app()->langPath();
        }

        // Fallback: Check for the new location first (Laravel 9+), then old location (Laravel 8-)
        $newLangPath = base_path('lang');
        $oldLangPath = resource_path('lang');

        // If the old location exists, use it (backward compatibility)
        // This matches Laravel's behavior: prioritize old location if it exists
        if (File::exists($oldLangPath)) {
            return $oldLangPath;
        }

        // Use the new location (Laravel 9+)
        return $newLangPath;
    }

    public function generateTranslationFiles(array $translationKeys, array $locales): bool
    {
        // Ensure the language directory exists before proceeding
        $this->ensureLangDirectoryExists();

        $success = true;

        foreach ($locales as $locale) {
            $existingTranslations = $this->loadExistingTranslations($locale);
            $newTranslations = $this->prepareTranslationsIncremental($translationKeys, $existingTranslations);

            if ($this->getLocalizationType() === 'json') {
                $success = $success && $this->generateJsonTranslationFile($locale, $newTranslations);
            } else {
                $success = $success && $this->generatePhpTranslationFile($locale, $newTranslations);
            }
        }

        return $success;
    }

    public function generateJsonTranslationFile(string $locale, array $translations): bool
    {
        // Ensure the language directory exists
        if (!File::exists($this->langPath)) {
            $this->ensureLangDirectoryExists();
        }
        
        $filePath = $this->langPath."/{$locale}.json";

        // Only create backup if explicitly requested (production-safe)
        if ($this->shouldCreateBackup() && File::exists($filePath)) {
            $this->createBackup($filePath);
        }

        if ($this->shouldSort) {
            ksort($translations);
        }

        $content = json_encode(
            $translations,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($content === false) {
            return false;
        }

        // JSON files don't support comments, so skip adding them for JSON format

        $result = File::put($filePath, $content);

        return $result !== false;
    }

    public function generatePhpTranslationFile(string $locale, array $translations): bool
    {
        // Ensure the language directory exists first
        if (!File::exists($this->langPath)) {
            $this->ensureLangDirectoryExists();
        }
        
        $success = true;
        $localeDir = $this->langPath."/{$locale}";

        if (! File::exists($localeDir)) {
            File::makeDirectory($localeDir, 0755, true);
        }

        $groupedTranslations = $this->groupTranslationsByFile($translations);

        foreach ($groupedTranslations as $file => $fileTranslations) {
            $filePath = $localeDir."/{$file}.php";

            // Only create backup if explicitly requested (production-safe)
            if ($this->shouldCreateBackup() && File::exists($filePath)) {
                $this->createBackup($filePath);
            }

            $success = $success && $this->writePhpTranslationFile($filePath, $fileTranslations, $locale, $file);
        }

        return $success;
    }

    public function mergeExistingTranslations(string $locale, array $newTranslations): array
    {
        $existingTranslations = $this->loadExistingTranslations($locale);

        // Merge new translations with existing ones, keeping existing values
        foreach ($newTranslations as $key => $value) {
            if (! isset($existingTranslations[$key])) {
                $existingTranslations[$key] = $value;
            }
        }

        // Remove missing translations if configured
        if (Config::get('localizator.remove_missing', false)) {
            $existingTranslations = array_intersect_key($existingTranslations, $newTranslations);
        }

        return $existingTranslations;
    }

    private function loadExistingTranslations(string $locale): array
    {
        $translations = [];

        if ($this->getLocalizationType() === 'json') {
            $filePath = $this->langPath."/{$locale}.json";
            if (File::exists($filePath)) {
                $content = File::get($filePath);
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $translations = $decoded;
                }
            }
        } else {
            $localeDir = $this->langPath."/{$locale}";
            if (File::exists($localeDir)) {
                $files = File::files($localeDir);
                foreach ($files as $file) {
                    if ($file->getExtension() === 'php') {
                        $fileName = $file->getFilenameWithoutExtension();
                        $fileTranslations = include $file->getRealPath();
                        if (is_array($fileTranslations)) {
                            if ($this->useNestedStructure) {
                                $this->flattenNestedTranslations($translations, $fileTranslations, $fileName);
                            } else {
                                foreach ($fileTranslations as $key => $value) {
                                    $translations["{$fileName}.{$key}"] = $value;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $translations;
    }

    private function prepareTranslations(array $translationKeys, array $existingTranslations): array
    {
        $translations = [];

        foreach ($translationKeys as $key) {
            if (isset($existingTranslations[$key])) {
                $translations[$key] = $existingTranslations[$key];
            } else {
                // Generate a sensible default value from the key
                $translations[$key] = $this->generateDefaultTranslation($key);
            }
        }

        return $translations;
    }

    /**
     * Prepare translations using incremental update logic (production-safe)
     * Only adds new keys, preserves all existing keys and values
     */
    private function prepareTranslationsIncremental(array $translationKeys, array $existingTranslations): array
    {
        // Start with all existing translations to preserve them
        $translations = $existingTranslations;

        // Only add new keys that don't exist yet
        foreach ($translationKeys as $key) {
            if (!isset($translations[$key])) {
                // Generate a sensible default value for new keys only
                $translations[$key] = $this->generateDefaultTranslation($key);
            }
            // If key already exists, leave its value unchanged (production-safe)
        }

        return $translations;
    }

    private function generateDefaultTranslation(string $key): string
    {
        // Extract the last part of the dot notation key
        $parts = explode('.', $key);
        $lastPart = end($parts);
        
        // Convert snake_case or kebab-case to Title Case
        $humanized = str_replace(['_', '-'], ' ', $lastPart);
        $humanized = ucwords($humanized);
        
        return $humanized;
    }

    /**
     * Check if backups should be created (production-safe: only when explicitly requested)
     */
    private function shouldCreateBackup(): bool
    {
        // Read fresh config to allow command overrides
        return Config::get('localizator.output.backup', false);
    }

    private function groupTranslationsByFile(array $translations): array
    {
        if (! $this->useNestedStructure) {
            // Legacy flat structure
            return $this->groupTranslationsByFileFlat($translations);
        }

        $grouped = [];

        foreach ($translations as $key => $value) {
            if (str_contains($key, '.')) {
                $keyParts = explode('.', $key);
                $file = array_shift($keyParts); // First part becomes the filename

                // Initialize file array if it doesn't exist
                if (! isset($grouped[$file])) {
                    $grouped[$file] = [];
                }

                // Create nested structure for remaining parts
                $this->setNestedValue($grouped[$file], $keyParts, $value);
            } else {
                // Keys without dots go to 'messages' file
                $file = 'messages';
                if (! isset($grouped[$file])) {
                    $grouped[$file] = [];
                }
                $grouped[$file][$key] = $value;
            }
        }

        return $grouped;
    }

    private function groupTranslationsByFileFlat(array $translations): array
    {
        $grouped = [];

        foreach ($translations as $key => $value) {
            if (str_contains($key, '.')) {
                [$file, $translationKey] = explode('.', $key, 2);
            } else {
                $file = 'messages';
                $translationKey = $key;
            }

            $grouped[$file][$translationKey] = $value;
        }

        return $grouped;
    }

    private function setNestedValue(array &$array, array $keys, string $value): void
    {
        $current = &$array;

        foreach ($keys as $key) {
            if (! isset($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }

        // Set the final value
        $current = $value;
    }

    private function flattenNestedTranslations(array &$result, array $translations, string $prefix, string $separator = '.'): void
    {
        foreach ($translations as $key => $value) {
            $newKey = $prefix ? "{$prefix}{$separator}{$key}" : $key;

            if (is_array($value)) {
                $this->flattenNestedTranslations($result, $value, $newKey, $separator);
            } else {
                $result[$newKey] = $value;
            }
        }
    }

    private function writePhpTranslationFile(string $filePath, array $translations, string $locale, string $file): bool
    {
        if ($this->shouldSort) {
            ksort($translations);
        }

        $content = "<?php\n\n";

        if ($this->generateComments) {
            $content .= "/**\n";
            $content .= " * Translation file: {$file}\n";
            $content .= " * Locale: {$locale}\n";
            $content .= " * Generated by Syriable Localizator\n";
            $content .= " */\n\n";
        }

        $content .= "return [\n";
        $content .= $this->arrayToString($translations, 1);
        $content .= "];\n";

        $result = File::put($filePath, $content);

        return $result !== false;
    }

    private function arrayToString(array $array, int $level): string
    {
        $content = '';
        $indentStr = str_repeat(' ', $this->indent * $level);

        foreach ($array as $key => $value) {
            $escapedKey = addslashes($key);

            if (is_array($value)) {
                $content .= "{$indentStr}'{$escapedKey}' => [\n";
                $content .= $this->arrayToString($value, $level + 1);
                $content .= "{$indentStr}],\n";
            } else {
                $escapedValue = addslashes($value);
                $content .= "{$indentStr}'{$escapedKey}' => '{$escapedValue}',\n";
            }
        }

        return $content;
    }

    private function createBackup(string $filePath): void
    {
        $timestamp = date('Y-m-d_H-i-s');
        $backupPath = $filePath.".backup_{$timestamp}";
        File::copy($filePath, $backupPath);
    }
}
