<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Localization Type
    |--------------------------------------------------------------------------
    |
    | This value determines the default type of localization files to generate.
    | Supported: "default" (PHP array), "json"
    |
    */
    'localize' => env('LOCALIZATOR_TYPE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Default Source Language
    |--------------------------------------------------------------------------
    |
    | The default language that will be used as the source for AI translations.
    | This should match one of your application's supported locales.
    |
    */
    'source_language' => env('LOCALIZATOR_SOURCE_LANG', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Default Locales
    |--------------------------------------------------------------------------
    |
    | Default locales to generate when using the automatic generate command
    | without specifying locales. These will be used by localizator:generate
    | when no locales are provided as arguments.
    |
    */
    'locales' => [
        'en',
        // Add more default locales as needed:
        // 'es', 'fr', 'de', 'it', 'pt', 'ru', 'ja', 'ko', 'zh'
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Directories
    |--------------------------------------------------------------------------
    |
    | These are the directories that will be scanned for translation functions.
    | You can add or remove directories as needed for your project structure.
    |
    */
    'dirs' => [
        app_path(),
        resource_path('views'),
        resource_path('js'),
        resource_path('vue'),
        base_path('routes'),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Patterns
    |--------------------------------------------------------------------------
    |
    | File patterns to include when scanning for translations.
    | These patterns use glob syntax.
    |
    */
    'patterns' => [
        '*.php',
        '*.blade.php',
        '*.vue',
        '*.js',
        '*.ts',
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Directories
    |--------------------------------------------------------------------------
    |
    | Directories to exclude from scanning. These are relative to the
    | directories specified in the 'dirs' configuration.
    |
    */
    'exclude' => [
        'vendor',
        'node_modules',
        'storage',
        'bootstrap/cache',
        '.git',
        'tests',
        'database/migrations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Translation Functions
    |--------------------------------------------------------------------------
    |
    | List of functions that are used for translations in your application.
    | The scanner will look for these function calls to extract strings.
    |
    */
    'functions' => [
        '__',
        'trans',
        'trans_choice',
        '@lang',
        '@choice',
        'Lang::get',
        'Lang::choice',
        'Lang::trans',
        'Lang::transChoice',
        '$t', // Vue i18n
        '$tc', // Vue i18n choice
    ],

    /*
    |--------------------------------------------------------------------------
    | Nested Structure
    |--------------------------------------------------------------------------
    |
    | Whether to use nested array structure based on dot notation keys.
    | When enabled, keys like 'auth.login.title' will create:
    | - File: resources/lang/en/auth.php
    | - Structure: ['login' => ['title' => '...']]
    |
    */
    'nested' => true,

    /*
    |--------------------------------------------------------------------------
    | Sort Translation Keys
    |--------------------------------------------------------------------------
    |
    | Whether to sort translation keys alphabetically in the generated files.
    |
    */
    'sort' => true,

    /*
    |--------------------------------------------------------------------------
    | Remove Missing Keys
    |--------------------------------------------------------------------------
    |
    | Whether to remove translation keys that are no longer found in the codebase.
    | Be careful with this option as it will permanently delete unused translations.
    |
    */
    'remove_missing' => false,

    /*
    |--------------------------------------------------------------------------
    | AI Translation Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for AI-powered translation features.
    |
    */
    'ai' => [
        /*
        |--------------------------------------------------------------------------
        | AI Provider
        |--------------------------------------------------------------------------
        |
        | The AI service provider to use for translations.
        | Supported: "openai", "claude", "google", "azure"
        |
        */
        'provider' => env('LOCALIZATOR_AI_PROVIDER', 'openai'),

        /*
        |--------------------------------------------------------------------------
        | API Configuration
        |--------------------------------------------------------------------------
        |
        | API keys and endpoints for AI translation services.
        |
        */
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-3.5-turbo'),
            'max_tokens' => 1000,
            'temperature' => 0.3,
        ],

        'claude' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('CLAUDE_MODEL', 'claude-3-sonnet-20240229'),
            'max_tokens' => 1000,
        ],

        'google' => [
            'api_key' => env('GOOGLE_TRANSLATE_API_KEY'),
            'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
        ],

        'azure' => [
            'api_key' => env('AZURE_TRANSLATOR_KEY'),
            'region' => env('AZURE_TRANSLATOR_REGION'),
            'endpoint' => env('AZURE_TRANSLATOR_ENDPOINT'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Translation Context
        |--------------------------------------------------------------------------
        |
        | Additional context to provide to AI for better translations.
        |
        */
        'context' => [
            'domain' => env('LOCALIZATOR_DOMAIN', 'general'),
            'tone' => env('LOCALIZATOR_TONE', 'neutral'),
            'additional_context' => env('LOCALIZATOR_ADDITIONAL_CONTEXT', ''),
        ],

        /*
        |--------------------------------------------------------------------------
        | Auto-translate Settings
        |--------------------------------------------------------------------------
        |
        | Settings for automatic translation features.
        |
        */
        'auto_translate' => env('LOCALIZATOR_AUTO_TRANSLATE', false),
        'review_required' => env('LOCALIZATOR_REVIEW_REQUIRED', true),
        'batch_size' => 50, // Number of strings to translate in one batch
        'rate_limit' => 60, // Requests per minute
    ],

    /*
    |--------------------------------------------------------------------------
    | Output Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for output formatting and file generation.
    |
    */
    'output' => [
        /*
        |--------------------------------------------------------------------------
        | Indentation
        |--------------------------------------------------------------------------
        |
        | Number of spaces to use for indentation in generated files.
        |
        */
        'indent' => 4,

        /*
        |--------------------------------------------------------------------------
        | Line Length
        |--------------------------------------------------------------------------
        |
        | Maximum line length for generated files before wrapping.
        |
        */
        'line_length' => 120,

        /*
        |--------------------------------------------------------------------------
        | Generate Comments
        |--------------------------------------------------------------------------
        |
        | Whether to include helpful comments in generated translation files.
        |
        */
        'comments' => true,

        /*
        |--------------------------------------------------------------------------
        | Backup Original Files
        |--------------------------------------------------------------------------
        |
        | Whether to create backups of existing translation files before updating.
        |
        */
        'backup' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Rules for validating translation keys and values.
    |
    */
    'validation' => [
        /*
        |--------------------------------------------------------------------------
        | Key Naming Convention
        |--------------------------------------------------------------------------
        |
        | Pattern for valid translation keys. Uses regex syntax.
        |
        */
        'key_pattern' => '/^[a-z0-9_\.]+$/',

        /*
        |--------------------------------------------------------------------------
        | Maximum Key Length
        |--------------------------------------------------------------------------
        |
        | Maximum allowed length for translation keys.
        |
        */
        'max_key_length' => 100,

        /*
        |--------------------------------------------------------------------------
        | Maximum Value Length
        |--------------------------------------------------------------------------
        |
        | Maximum allowed length for translation values.
        |
        */
        'max_value_length' => 1000,

        /*
        |--------------------------------------------------------------------------
        | Required Placeholders
        |--------------------------------------------------------------------------
        |
        | Whether to validate that placeholders in source strings
        | are present in translated strings.
        |
        */
        'validate_placeholders' => true,
    ],
];
