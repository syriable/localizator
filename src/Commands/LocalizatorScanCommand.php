<?php

declare(strict_types=1);

namespace Syriable\Localizator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Syriable\Localizator\Contracts\FileScanner;
use Syriable\Localizator\Contracts\TranslationGenerator;
use Syriable\Localizator\Contracts\TranslationService;

class LocalizatorScanCommand extends Command
{
    protected $signature = 'localizator:scan 
                            {locales?* : The locales to generate translations for}
                            {--remove-missing : Remove translation keys that are no longer found in the codebase}
                            {--sort : Sort translation keys alphabetically}
                            {--auto-translate : Automatically translate missing keys using AI}
                            {--review : Review AI translations before applying (used with --auto-translate)}
                            {--source-lang=en : Source language for AI translations}
                            {--provider= : AI provider to use (openai, claude, google, azure)}
                            {--batch-size=50 : Number of translations to process in each batch}
                            {--format= : Output format (php, json) - overrides config setting}
                            {--dry-run : Show what would be done without actually doing it}
                            {--backup : Create backup of existing translation files}';

    protected $description = 'Scan the Laravel application for translation strings and generate translation files';

    private FileScanner $fileScanner;

    private TranslationGenerator $translationGenerator;

    private TranslationService $translationService;

    public function __construct(
        FileScanner $fileScanner,
        TranslationGenerator $translationGenerator,
        TranslationService $translationService
    ) {
        parent::__construct();

        $this->fileScanner = $fileScanner;
        $this->translationGenerator = $translationGenerator;
        $this->translationService = $translationService;
    }

    public function handle(): int
    {
        $this->info('🔍 Starting Localizator scan...');

        // Get configuration
        $directories = Config::get('localizator.dirs', []);
        $locales = $this->getLocales();
        $shouldRemoveMissing = (bool) ($this->option('remove-missing') || Config::get('localizator.remove_missing', false));
        $shouldSort = (bool) ($this->option('sort') || Config::get('localizator.sort', true));
        $shouldAutoTranslate = (bool) ($this->option('auto-translate') || Config::get('localizator.ai.auto_translate', false));
        $shouldReview = (bool) ($this->option('review') || Config::get('localizator.ai.review_required', true));
        $isDryRun = (bool) $this->option('dry-run');

        // Override AI provider if specified
        if ($provider = $this->option('provider')) {
            Config::set('localizator.ai.provider', $provider);
        }

        // Override output format if specified
        if ($format = $this->option('format')) {
            if (! in_array($format, ['php', 'json', 'default'])) {
                $this->error('❌ Invalid format. Supported formats: php, json');
                return self::FAILURE;
            }
            
            // Convert 'php' to 'default' for internal consistency
            $format = $format === 'php' ? 'default' : $format;
            Config::set('localizator.localize', $format);
            
            $this->info("📄 Using {$this->option('format')} format");
        }

        // Handle backup setting (production-safe)
        if ($this->option('backup')) {
            Config::set('localizator.output.backup', true);
            $this->info('🗃️  Backup mode enabled');
        }
        // Otherwise, use default config setting (false by default for production safety)

        try {
            // Step 1: Scan for translation keys
            $this->info('📁 Scanning directories for translation functions...');
            $translationKeys = $this->fileScanner->scanDirectories($directories);

            $this->info('✅ Found '.count($translationKeys).' translation keys');

            if ($this->output->isVerbose()) {
                $this->table(['Translation Keys'], array_map(fn ($key) => [$key], $translationKeys));
            }

            if (empty($translationKeys)) {
                $this->warn('No translation keys found. Make sure your functions are configured correctly.');

                return self::SUCCESS;
            }

            // Step 2: Process each locale
            foreach ($locales as $locale) {
                $this->processLocale($locale, $translationKeys, $shouldAutoTranslate, $shouldReview, $isDryRun);
            }

            // Step 3: Generate translation files (if not dry run)
            if (! $isDryRun) {
                $this->info('📝 Generating translation files...');

                if ($this->translationGenerator->generateTranslationFiles($translationKeys, $locales)) {
                    $this->info('✅ Translation files generated successfully!');
                } else {
                    $this->error('❌ Failed to generate some translation files');

                    return self::FAILURE;
                }
            } else {
                $this->info('🔍 Dry run completed. No files were modified.');
            }

            $this->displaySummary($translationKeys, $locales, $shouldAutoTranslate, $isDryRun);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");

            if ($this->output->isVerbose()) {
                $this->error($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    private function getLocales(): array
    {
        $locales = $this->argument('locales');

        if (empty($locales)) {
            $locales = $this->askForLocales();
        }

        if (empty($locales)) {
            $this->error('❌ No locales specified. Please provide at least one locale.');
            exit(self::FAILURE);
        }

        return is_array($locales) ? $locales : [$locales];
    }

    private function askForLocales(): array
    {
        $this->info('Please specify the locales you want to generate translations for:');

        $supportedLanguages = $this->translationService->getSupportedLanguages();
        $options = [];

        foreach ($supportedLanguages as $code => $name) {
            $options[] = "{$code} - {$name}";
        }

        $selected = $this->choice(
            'Select locales (comma-separated for multiple)',
            $options,
            null,
            null,
            true
        );

        return array_map(function (string $option): string {
            return explode(' - ', $option)[0];
        }, is_array($selected) ? $selected : [$selected]);
    }

    private function processLocale(
        string $locale,
        array $translationKeys,
        bool $shouldAutoTranslate,
        bool $shouldReview,
        bool $isDryRun
    ): void {
        $this->info("🌐 Processing locale: {$locale}");

        // Load existing translations
        $existingTranslations = $this->translationGenerator->mergeExistingTranslations($locale, array_fill_keys($translationKeys, ''));

        // Find missing translations
        $missingKeys = [];
        foreach ($translationKeys as $key) {
            if (empty($existingTranslations[$key]) || $existingTranslations[$key] === $key) {
                $missingKeys[] = $key;
            }
        }

        if (empty($missingKeys)) {
            $this->info("  ✅ All translations exist for {$locale}");

            return;
        }

        $this->info('  📝 Found '.count($missingKeys)." missing translations for {$locale}");

        if ($shouldAutoTranslate) {
            $this->autoTranslateMissingKeys($locale, $missingKeys, $shouldReview, $isDryRun);
        } else {
            $this->listMissingKeys($missingKeys);
        }
    }

    private function autoTranslateMissingKeys(
        string $locale,
        array $missingKeys,
        bool $shouldReview,
        bool $isDryRun
    ): void {
        $sourceLanguage = $this->option('source-lang') ?: Config::get('localizator.source_language', 'en');
        $batchSize = (int) $this->option('batch-size') ?: Config::get('localizator.ai.batch_size', 50);

        if ($locale === $sourceLanguage) {
            $this->warn("  ⚠️  Skipping auto-translation for source language ({$sourceLanguage})");

            return;
        }

        $this->info("  🤖 Auto-translating from {$sourceLanguage} to {$locale}...");

        $batches = array_chunk($missingKeys, $batchSize);
        $translations = [];

        foreach ($batches as $batchIndex => $batch) {
            $this->info('  📦 Processing batch '.($batchIndex + 1).' of '.count($batches));

            if (! $isDryRun) {
                try {
                    $batchTranslations = $this->translationService->translateBatch(
                        $batch,
                        $sourceLanguage,
                        $locale
                    );

                    $translations = array_merge($translations, array_combine($batch, $batchTranslations));

                    // Rate limiting
                    if ($batchIndex < count($batches) - 1) {
                        $this->info('  ⏱️  Waiting to respect rate limits...');
                        sleep(1); // Simple rate limiting
                    }
                } catch (\Exception $e) {
                    $this->error("  ❌ Failed to translate batch: {$e->getMessage()}");

                    continue;
                }
            } else {
                // For dry run, just simulate translations
                foreach ($batch as $key) {
                    $translations[$key] = "[TRANSLATED] {$key}";
                }
            }
        }

        if ($shouldReview && ! $isDryRun) {
            $translations = $this->reviewTranslations($translations, $locale);
        }

        if (! empty($translations)) {
            $this->displayTranslations($translations, $locale);
        }
    }

    private function reviewTranslations(array $translations, string $locale): array
    {
        $this->info("📋 Reviewing translations for {$locale}:");
        $reviewedTranslations = [];

        foreach ($translations as $key => $translation) {
            $this->line("  Key: <info>{$key}</info>");
            $this->line("  Translation: <comment>{$translation}</comment>");

            if ($this->confirm('Accept this translation?', true)) {
                $reviewedTranslations[$key] = $translation;
            } else {
                $customTranslation = $this->ask('Enter your translation (or press Enter to skip)');
                if (! empty($customTranslation)) {
                    $reviewedTranslations[$key] = $customTranslation;
                }
            }

            $this->line('');
        }

        return $reviewedTranslations;
    }

    private function listMissingKeys(array $missingKeys): void
    {
        if ($this->output->isVerbose()) {
            $this->line('  Missing translation keys:');
            foreach ($missingKeys as $key) {
                $this->line("    - {$key}");
            }
        }
    }

    private function displayTranslations(array $translations, string $locale): void
    {
        if ($this->output->isVerbose()) {
            $this->table(
                ["Key ({$locale})", 'Translation'],
                array_map(fn ($key, $translation) => [$key, $translation], array_keys($translations), $translations)
            );
        }
    }

    private function displaySummary(
        array $translationKeys,
        array $locales,
        bool $shouldAutoTranslate,
        bool $isDryRun
    ): void {
        $this->info('📊 Summary:');
        $this->line('  • Total translation keys found: '.count($translationKeys));
        $this->line('  • Locales processed: '.implode(', ', $locales));
        $this->line('  • Auto-translation: '.($shouldAutoTranslate ? 'Enabled' : 'Disabled'));
        $this->line('  • Mode: '.($isDryRun ? 'Dry run' : 'Live'));

        if (! $isDryRun) {
            $this->info('🎉 Localizator scan completed successfully!');

            $this->line('');
            $this->line('💡 Next steps:');
            $this->line('  • Review the generated translation files');
            $this->line('  • Test your translations in the application');
            $this->line('  • Consider running with --auto-translate for AI-powered translations');
        }
    }
}
