<?php

declare(strict_types=1);

namespace Syriable\Localizator\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;
use Syriable\Localizator\Contracts\FileScanner;

class FileScannerService implements FileScanner
{
    private array $functions;

    private array $patterns;

    private array $excludes;

    public function __construct()
    {
        $this->functions = Config::get('localizator.functions', []);
        $this->patterns = Config::get('localizator.patterns', []);
        $this->excludes = Config::get('localizator.exclude', []);
    }

    public function scanDirectories(array $directories): array
    {
        $translationKeys = [];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $files = $this->getFilesFromDirectory($directory);

            foreach ($files as $file) {
                $keys = $this->extractTranslationKeys($file->getRealPath());
                $translationKeys = array_merge($translationKeys, $keys);
            }
        }

        return array_unique($translationKeys);
    }

    public function extractTranslationKeys(string $filePath): array
    {
        if (! File::exists($filePath)) {
            return [];
        }

        $content = File::get($filePath);

        return $this->findTranslationFunction($content);
    }

    public function findTranslationFunction(string $content): array
    {
        $keys = [];

        // Remove commented/disabled translation keys before extraction
        $content = $this->removeCommentedTranslations($content);

        foreach ($this->functions as $function) {
            $keys = array_merge($keys, $this->extractKeysForFunction($content, $function));
        }

        return array_unique(array_filter($keys));
    }

    private function getFilesFromDirectory(string $directory): Finder
    {
        $finder = new Finder;
        $finder->files()->in($directory);

        // Apply patterns
        foreach ($this->patterns as $pattern) {
            $finder->name($pattern);
        }

        // Apply excludes
        foreach ($this->excludes as $exclude) {
            $finder->exclude($exclude)->notPath($exclude);
        }

        return $finder;
    }

    private function extractKeysForFunction(string $content, string $function): array
    {
        $keys = [];

        // Handle different function types
        switch ($function) {
            case '__':
            case 'trans':
            case 'trans_choice':
                $keys = array_merge($keys, $this->extractPhpFunctionKeys($content, $function));
                break;

            case '@lang':
            case '@choice':
                $keys = array_merge($keys, $this->extractBladeDirectiveKeys($content, $function));
                break;

            case 'Lang::get':
            case 'Lang::choice':
            case 'Lang::trans':
            case 'Lang::transChoice':
                $keys = array_merge($keys, $this->extractStaticMethodKeys($content, $function));
                break;

            case '$t':
            case '$tc':
                $keys = array_merge($keys, $this->extractVueI18nKeys($content, $function));
                break;
        }

        return $keys;
    }

    private function extractPhpFunctionKeys(string $content, string $function): array
    {
        $keys = [];

        // Match function calls: __('key'), trans('key'), etc.
        $pattern = '/'.preg_quote($function, '/').'\s*\(\s*(["\'])((?:[^"\'\\\\]|\\\\.)*)\\1/';

        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[2] as $key) {
                $cleanKey = $this->cleanTranslationKey($key);
                if (! empty($cleanKey)) {
                    $keys[] = $cleanKey;
                }
            }
        }

        return $keys;
    }

    private function extractBladeDirectiveKeys(string $content, string $directive): array
    {
        $keys = [];

        // Match Blade directives: @lang('key'), @choice('key', $count), etc.
        $pattern = '/'.preg_quote($directive, '/').'\s*\(\s*(["\'])((?:[^"\'\\\\]|\\\\.)*)\\1/';

        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[2] as $key) {
                $cleanKey = $this->cleanTranslationKey($key);
                if (! empty($cleanKey)) {
                    $keys[] = $cleanKey;
                }
            }
        }

        return $keys;
    }

    private function extractStaticMethodKeys(string $content, string $method): array
    {
        $keys = [];

        // Match static method calls: Lang::get('key'), etc.
        $pattern = '/'.preg_quote($method, '/').'\s*\(\s*(["\'])((?:[^"\'\\\\]|\\\\.)*)\\1/';

        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[2] as $key) {
                $cleanKey = $this->cleanTranslationKey($key);
                if (! empty($cleanKey)) {
                    $keys[] = $cleanKey;
                }
            }
        }

        return $keys;
    }

    private function extractVueI18nKeys(string $content, string $method): array
    {
        $keys = [];

        // Match Vue i18n calls: $t('key'), $tc('key'), etc.
        // Also match template usage: {{ $t('key') }}
        $patterns = [
            '/'.preg_quote($method, '/').'\s*\(\s*(["\'])((?:[^"\'\\\\]|\\\\.)*)\\1\s*\)/',  // JavaScript
            '/\{\{\s*'.preg_quote($method, '/').'\s*\(\s*(["\'])((?:[^"\'\\\\]|\\\\.)*)\\1\s*(?:,.*?)?\s*\)\s*\}\}/', // Template
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[2] as $key) {
                    $cleanKey = $this->cleanTranslationKey($key);
                    if (! empty($cleanKey)) {
                        $keys[] = $cleanKey;
                    }
                }
            }
        }

        return $keys;
    }

    private function cleanTranslationKey(string $key): string
    {
        // Remove escape characters and clean up the key
        $key = stripslashes($key);

        // Remove any surrounding whitespace
        $key = trim($key);

        // Skip empty keys or keys that are variables/placeholders
        if (empty($key) || $this->isVariable($key)) {
            return '';
        }

        return $key;
    }

    private function isVariable(string $key): bool
    {
        // Check if the key looks like a variable (starts with $, contains {}, etc.)
        return str_starts_with($key, '$') ||
               str_contains($key, '{{') ||
               str_contains($key, '{$') ||
               str_contains($key, '${') ||
               preg_match('/^\$[a-zA-Z_]/', $key) === 1; // Starts with variable syntax
    }

    /**
     * Remove commented/disabled translation keys from content
     * Supports various comment formats:
     * - C-style comments: slash-star content star-slash 
     * - Single-line comments: // content
     * - Blade comments: {{-- content --}}
     * - HTML comments: <!-- content -->
     */
    private function removeCommentedTranslations(string $content): string
    {
        $patterns = [
            // Multi-line C-style comments: /* {{ __('key') }} */
            '/\/\*.*?\*\//s',
            
            // Single-line C++ style comments: // {{ __('key') }}
            '/\/\/.*?(?=\n|$)/m',
            
            // Blade comments: {{-- __('key') --}}
            '/\{\{--.*?--\}\}/s',
            
            // HTML comments: <!-- {{ __('key') }} -->
            '/<!--.*?-->/s',
        ];

        foreach ($patterns as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }

        return $content;
    }
}
