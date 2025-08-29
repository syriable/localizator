<?php

declare(strict_types=1);

namespace Syriable\Localizator\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Syriable\Localizator\LocalizatorServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->app) {
            $this->app['config']->set('localizator', [
                'localize' => 'default',
                'source_language' => 'en',
                'dirs' => [
                    app_path(),
                    resource_path('views'),
                ],
                'patterns' => [
                    '*.php',
                    '*.blade.php',
                ],
                'exclude' => [
                    'vendor',
                    'node_modules',
                    'storage',
                    'bootstrap/cache',
                    '.git',
                    'tests',
                ],
                'functions' => [
                    '__',
                    'trans',
                    'trans_choice',
                    '@lang',
                    '@choice',
                    '$t',
                    '$tc',
                ],
                'nested' => true,
                'sort' => true,
                'remove_missing' => false,
                'ai' => [
                    'provider' => 'openai',
                    'openai' => [
                        'api_key' => 'test-key',
                        'model' => 'gpt-3.5-turbo',
                        'max_tokens' => 1000,
                        'temperature' => 0.3,
                    ],
                    'context' => [
                        'domain' => 'general',
                        'tone' => 'neutral',
                        'additional_context' => '',
                    ],
                    'auto_translate' => false,
                    'review_required' => true,
                    'batch_size' => 50,
                    'rate_limit' => 60,
                ],
                'output' => [
                    'indent' => 4,
                    'line_length' => 120,
                    'comments' => true,
                    'backup' => true,
                ],
                'validation' => [
                    'key_pattern' => '/^[a-z0-9_\.]+$/',
                    'max_key_length' => 100,
                    'max_value_length' => 1000,
                    'validate_placeholders' => true,
                ],
            ]);
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            LocalizatorServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup the application environment for testing
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
