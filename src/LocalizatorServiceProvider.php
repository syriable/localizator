<?php

declare(strict_types=1);

namespace Syriable\Localizator;

use Illuminate\Support\ServiceProvider;
use Syriable\Localizator\Commands\LocalizatorScanCommand;
use Syriable\Localizator\Commands\LocalizatorGenerateCommand;
use Syriable\Localizator\Contracts\FileScanner;
use Syriable\Localizator\Contracts\TranslationGenerator;
use Syriable\Localizator\Contracts\TranslationService;
use Syriable\Localizator\Services\AITranslationService;
use Syriable\Localizator\Services\FileScannerService;
use Syriable\Localizator\Services\TranslationGeneratorService;

class LocalizatorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/localizator.php',
            'localizator'
        );

        $this->app->bind(FileScanner::class, FileScannerService::class);
        $this->app->bind(TranslationGenerator::class, TranslationGeneratorService::class);
        $this->app->bind(TranslationService::class, AITranslationService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                LocalizatorScanCommand::class,
                LocalizatorGenerateCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/localizator.php' => config_path('localizator.php'),
            ], 'localizator-config');

            $this->publishes([
                __DIR__.'/../resources/lang' => resource_path('lang/vendor/localizator'),
            ], 'localizator-lang');
        }
    }
}
