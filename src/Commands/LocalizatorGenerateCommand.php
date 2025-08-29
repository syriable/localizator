<?php

declare(strict_types=1);

namespace Syriable\Localizator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Syriable\Localizator\Contracts\FileScanner;
use Syriable\Localizator\Contracts\TranslationGenerator;
use Syriable\Localizator\Contracts\TranslationService;

class LocalizatorGenerateCommand extends Command
{
    protected $signature = 'localizator:generate 
                            {locales?* : The locales to generate translations for (defaults to config)}
                            {--auto-translate : Automatically translate missing keys using AI}
                            {--source-lang=en : Source language for AI translations}
                            {--provider= : AI provider to use (openai, claude, google, azure)}
                            {--batch-size=50 : Number of translations to process in each batch}
                            {--force : Overwrite existing translation files without backup}
                            {--silent : Suppress all output except errors}';

    protected $description = 'Generate all translation files automatically without any interactive prompts';

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
        $isSilent = (bool) $this->option('silent');

        if (! $isSilent) {
            $this->info('🚀 Starting automatic translation generation...');
        }

        // Get configuration
        $directories = Config::get('localizator.dirs', []);
        $locales = $this->getLocales();
        $shouldAutoTranslate = (bool) ($this->option('auto-translate') || Config::get('localizator.ai.auto_translate', false));
        $isForced = (bool) $this->option('force');

        // Override configurations for automatic mode
        Config::set('localizator.remove_missing', false);
        Config::set('localizator.sort', true);
        Config::set('localizator.ai.review_required', false); // No review in automatic mode
        
        if (! $isForced) {
            Config::set('localizator.output.backup', true); // Always backup unless forced
        }

        // Override AI provider if specified
        if ($provider = $this->option('provider')) {
            Config::set('localizator.ai.provider', $provider);
        }

        try {
            // Step 1: Scan for translation keys
            if (! $isSilent) {
                $this->info('📁 Scanning directories for translation functions...');
            }
            
            $translationKeys = $this->fileScanner->scanDirectories($directories);

            if (! $isSilent) {
                $this->info('✅ Found '.count($translationKeys).' translation keys');
            }

            if (empty($translationKeys)) {
                if (! $isSilent) {
                    $this->warn('No translation keys found. Make sure your functions are configured correctly.');
                }
                return self::SUCCESS;
            }

            // Step 2: Process each locale automatically
            foreach ($locales as $locale) {
                $this->processLocaleAutomatically($locale, $translationKeys, $shouldAutoTranslate, $isSilent);
            }

            // Step 3: Generate translation files
            if (! $isSilent) {
                $this->info('📝 Generating translation files...');
            }

            if ($this->translationGenerator->generateTranslationFiles($translationKeys, $locales)) {
                if (! $isSilent) {
                    $this->info('✅ Translation files generated successfully!');
                    $this->displaySummary($translationKeys, $locales, $shouldAutoTranslate);
                }
            } else {
                $this->error('❌ Failed to generate some translation files');
                return self::FAILURE;
            }

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
            // Get default locales from configuration
            $locales = Config::get('localizator.locales', ['en']);
        }

        if (empty($locales)) {
            $this->error('❌ No locales specified and no default locales configured.');
            exit(self::FAILURE);
        }

        return is_array($locales) ? $locales : [$locales];
    }

    private function processLocaleAutomatically(
        string $locale,
        array $translationKeys,
        bool $shouldAutoTranslate,
        bool $isSilent
    ): void {
        if (! $isSilent) {
            $this->info("🌐 Processing locale: {$locale}");
        }

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
            if (! $isSilent) {
                $this->info("  ✅ All translations exist for {$locale}");
            }
            return;
        }

        if (! $isSilent) {
            $this->info('  📝 Found '.count($missingKeys)." missing translations for {$locale}");
        }

        if ($shouldAutoTranslate) {
            $this->autoTranslateMissingKeysAutomatically($locale, $missingKeys, $isSilent);
        }
    }

    private function autoTranslateMissingKeysAutomatically(
        string $locale,
        array $missingKeys,
        bool $isSilent
    ): void {
        $sourceLanguage = $this->option('source-lang') ?: Config::get('localizator.source_language', 'en');
        $batchSize = (int) $this->option('batch-size') ?: Config::get('localizator.ai.batch_size', 50);

        if ($locale === $sourceLanguage) {
            if (! $isSilent) {
                $this->warn("  ⚠️  Skipping auto-translation for source language ({$sourceLanguage})");
            }
            return;
        }

        if (! $isSilent) {
            $this->info("  🤖 Auto-translating from {$sourceLanguage} to {$locale}...");
        }

        $batches = array_chunk($missingKeys, $batchSize);
        $translations = [];

        foreach ($batches as $batchIndex => $batch) {
            if (! $isSilent) {
                $this->info('  📦 Processing batch '.($batchIndex + 1).' of '.count($batches));
            }

            try {
                $batchTranslations = $this->translationService->translateBatch(
                    $batch,
                    $sourceLanguage,
                    $locale
                );

                $translations = array_merge($translations, array_combine($batch, $batchTranslations));

                // Rate limiting
                if ($batchIndex < count($batches) - 1) {
                    if (! $isSilent) {
                        $this->info('  ⏱️  Waiting to respect rate limits...');
                    }
                    sleep(1);
                }
            } catch (\Exception $e) {
                if (! $isSilent) {
                    $this->error("  ❌ Failed to translate batch: {$e->getMessage()}");
                }
                continue;
            }
        }

        if (! empty($translations) && ! $isSilent) {
            $this->info('  ✅ Translated '.count($translations).' keys for '.$locale);
        }
    }

    private function displaySummary(
        array $translationKeys,
        array $locales,
        bool $shouldAutoTranslate
    ): void {
        $this->info('📊 Summary:');
        $this->line('  • Total translation keys found: '.count($translationKeys));
        $this->line('  • Locales processed: '.implode(', ', $locales));
        $this->line('  • Auto-translation: '.($shouldAutoTranslate ? 'Enabled' : 'Disabled'));

        $this->info('🎉 Automatic translation generation completed!');

        $this->line('');
        $this->line('💡 Generated files:');
        
        foreach ($locales as $locale) {
            $this->line("  • Translation files for '{$locale}' locale");
        }
    }
}