# Syriable Localizator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/syriable/localizator.svg?style=flat-square)](https://packagist.org/packages/syriable/localizator)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/syriable/localizator/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/syriable/localizator/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/syriable/localizator/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/syriable/localizator/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/syriable/localizator.svg?style=flat-square)](https://packagist.org/packages/syriable/localizator)

An advanced Laravel package for automatic translation scanning and AI-powered translation generation. Built for Laravel 12+ with modern PHP standards and FilamentPHP 4 compatibility.

## 🚀 Features

- **🔍 Comprehensive Scanning**: Automatically scan PHP, Blade, Vue.js, and JavaScript files for translation functions
- **🚫 Smart Comment Detection**: Automatically skip translation keys in comments (`/* {{ __('key') }} */`)
- **🧹 Remove Missing Keys**: Clean up unused translation keys from language files with `--remove-missing`
- **🤖 AI-Powered Translations**: Generate translations using OpenAI, Claude, Google Translate, or Azure Translator
- **📁 Multiple Formats**: Support for both JSON and PHP array translation files
- **⚙️ Highly Configurable**: Extensive configuration options for customizing behavior
- **🧪 Production Ready**: Built with PSR standards, PHPStan level 8, and comprehensive testing
- **🔧 Developer Friendly**: Interactive CLI commands with progress indicators and validation
- **💾 Safe Operations**: Automatic backups and dry-run capabilities

## 📋 Requirements

- PHP 8.3 or higher
- Laravel 12.0 or higher
- Composer

## 📦 Installation

Install the package via Composer:

```bash
composer require syriable/localizator
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="localizator-config"
```

This will publish the configuration file to `config/localizator.php`.

## ⚙️ Configuration

The package comes with extensive configuration options. Here are the key settings:

```php
// config/localizator.php
return [
    // Translation file type: 'default' (PHP) or 'json'
    'localize' => env('LOCALIZATOR_TYPE', 'default'),
    
    // Source language for AI translations
    'source_language' => env('LOCALIZATOR_SOURCE_LANG', 'en'),
    
    // Use nested array structure for dot notation keys
    'nested' => true,
    
    // Directories to scan for translation functions
    'dirs' => [
        app_path(),
        resource_path('views'),
        resource_path('js'),
        resource_path('vue'),
        base_path('routes'),
    ],
    
    // File patterns to include
    'patterns' => [
        '*.php',
        '*.blade.php',
        '*.vue',
        '*.js',
        '*.ts',
    ],
    
    // Translation functions to look for
    'functions' => [
        '__',
        'trans',
        'trans_choice',
        '@lang',
        '@choice',
        'Lang::get',
        'Lang::choice',
        '$t',  // Vue i18n
        '$tc', // Vue i18n choice
    ],
    
    // AI translation settings
    'ai' => [
        'provider' => env('LOCALIZATOR_AI_PROVIDER', 'openai'),
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-3.5-turbo'),
        ],
        'claude' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('CLAUDE_MODEL', 'claude-3-sonnet-20240229'),
        ],
        // ... more AI provider configurations
    ],
];
```

### Environment Variables

Add these to your `.env` file:

```env
# Basic Configuration
LOCALIZATOR_TYPE=default
LOCALIZATOR_SOURCE_LANG=en

# AI Provider (choose one)
LOCALIZATOR_AI_PROVIDER=openai

# OpenAI Configuration
OPENAI_API_KEY=your-openai-api-key
OPENAI_MODEL=gpt-3.5-turbo

# Or Claude Configuration
ANTHROPIC_API_KEY=your-anthropic-api-key
CLAUDE_MODEL=claude-3-sonnet-20240229

# Or Google Translate Configuration
GOOGLE_TRANSLATE_API_KEY=your-google-api-key
GOOGLE_CLOUD_PROJECT_ID=your-project-id

# Or Azure Translator Configuration
AZURE_TRANSLATOR_KEY=your-azure-key
AZURE_TRANSLATOR_REGION=your-region
AZURE_TRANSLATOR_ENDPOINT=your-endpoint
```

## 🛠️ Usage

### Automatic Generation (Recommended)

Generate all translation files automatically without any interactive prompts:

```bash
# Generate for default locales (configured in config/localizator.php)
php artisan localizator:generate

# Generate for specific locales
php artisan localizator:generate en es fr de

# Silent mode (no output except errors)
php artisan localizator:generate --silent

# With AI auto-translation
php artisan localizator:generate es fr --auto-translate

# Generate JSON files instead of PHP
php artisan localizator:generate en --format=json

# Force overwrite without backup
php artisan localizator:generate en --force
```

### Interactive Scanning

Scan your application with interactive prompts and review options:

```bash
php artisan localizator:scan en es fr de
```

### Advanced Options

The scan command supports various options for different use cases:

```bash
# Dry run - see what would be done without making changes
php artisan localizator:scan en --dry-run

# Remove missing translation keys (clean up unused keys)
php artisan localizator:scan en --remove-missing

# Sort translation keys alphabetically
php artisan localizator:scan en --sort

# Enable AI-powered auto-translation
php artisan localizator:scan es fr --auto-translate

# Auto-translate with review process
php artisan localizator:scan es --auto-translate --review

# Specify AI provider and source language
php artisan localizator:scan es --auto-translate --provider=claude --source-lang=en

# Custom batch size for AI translations
php artisan localizator:scan es --auto-translate --batch-size=25

# Generate JSON files instead of PHP
php artisan localizator:scan en --format=json

# Create backups of existing files
php artisan localizator:scan en --backup

# Verbose output for debugging
php artisan localizator:scan en -v
```

### Command Comparison

| Feature | `localizator:generate` | `localizator:scan` |
|---------|----------------------|-------------------|
| **Interaction** | Fully automatic, zero prompts | Interactive with prompts and choices |
| **Locale Selection** | Uses config defaults or arguments | Prompts for locale selection if not specified |
| **AI Review** | Auto-accepts all translations | Optional review process for AI translations |
| **Best for** | CI/CD, automated workflows | Manual control, reviewing translations |
| **Speed** | Fast, no waiting for input | Slower due to interactive prompts |
| **Silent Mode** | Yes (`--silent` flag) | No |
| **Configuration** | Uses `locales` from config | Always prompts if no arguments |

**Recommendation**: Use `localizator:generate` for most cases, especially in automated environments. Use `localizator:scan` when you need to review and approve translations manually.

### Interactive Mode

When you run the command without specifying locales, it will prompt you to select from supported languages:

```bash
php artisan localizator:scan
```

## 🔍 What Gets Scanned

The package automatically detects and extracts translation keys from:

### Comment Detection & Skipping

**NEW in v1.4.0**: The package now automatically skips translation keys found in comments, preventing disabled or temporary keys from being added to your language files.

**Supported Comment Types:**
```php
// C-style comments
/* {{ __('disabled.key') }} */

// Single-line comments  
// {{ __('commented.key') }}

// Blade comments
{{-- {{ __('blade.disabled.key') }} --}}

// HTML comments
<!-- {{ __('html.commented.key') }} -->

// Multiline comments
/*
 * {{ __('multiline.disabled.key1') }}
 * {{ __('multiline.disabled.key2') }}
 */
```

**Why This Matters:**
- ✅ Prevents cluttering language files with temporary/disabled keys
- ✅ Keeps your translations clean and organized  
- ✅ Allows developers to comment out translations without affecting builds
- ✅ Works automatically - no configuration needed

### File Types Scanned

### PHP Files
```php
__('auth.login.title')           // → resources/lang/en/auth.php: ['login' => ['title' => '...']]
trans('validation.email.required') // → resources/lang/en/validation.php: ['email' => ['required' => '...']]
trans_choice('messages.items', $count)
Lang::get('dashboard.widgets.summary')
Lang::choice('items.count', 5)
```

### Blade Templates
```blade
@lang('app.title')
@choice('messages.items', $itemCount)
{{ __('buttons.submit') }}
{{ trans('labels.name') }}
```

### Vue.js Components
```vue
<template>
  <h1>{{ $t('dashboard.title') }}</h1>
  <p>{{ $tc('users.count', userCount) }}</p>
</template>

<script>
this.$t('notifications.success')
this.$tc('items.found', itemsFound)
</script>
```

### JavaScript Files
```javascript
$t('messages.welcome')
$tc('items.selected', selectedCount)
```

## 🏗️ Intelligent Nested Structure

The package automatically organizes translation files based on dot notation keys, following Laravel's best practices:

### How It Works

**Translation Key** → **Generated File Structure**

- `auth.login.title` → `resources/lang/en/auth.php`
  ```php
  return [
      'login' => [
          'title' => 'Login Title'
      ]
  ];
  ```

- `validation.custom.email.required` → `resources/lang/en/validation.php`
  ```php
  return [
      'custom' => [
          'email' => [
              'required' => 'Email is required'
          ]
      ]
  ];
  ```

### Key Benefits

✅ **Organized Structure**: Files are automatically organized by context  
✅ **Laravel Standard**: Follows Laravel's recommended translation file structure  
✅ **Deep Nesting**: Supports unlimited nesting levels (`auth.forms.login.validation.required`)  
✅ **Backward Compatible**: Can be disabled with `'nested' => false`  
✅ **Merge Existing**: Intelligently merges with existing translation files  

## 🧹 Remove Missing Keys

**NEW in v1.4.0**: The `--remove-missing` option has been improved to properly clean up unused translation keys from your language files.

### How It Works

When you delete translation keys from your source code, they remain in your language files. The `--remove-missing` flag solves this by:

1. **Scanning** your codebase for currently used translation keys
2. **Comparing** with existing translation files  
3. **Removing** keys that no longer exist in your source code
4. **Preserving** all keys that are still in use

### Usage Examples

```bash
# Remove unused keys from English translations
php artisan localizator:scan en --remove-missing

# Remove unused keys and create backups
php artisan localizator:scan en --remove-missing --backup

# See what would be removed without making changes
php artisan localizator:scan en --remove-missing --dry-run

# Remove unused keys from multiple locales
php artisan localizator:scan en es fr --remove-missing
```

### Before & After Example

**Before** (your source code changed):
```php
// You removed this from your Blade file:
// {{ __('auth.old_feature') }}

// But kept this:
{{ __('auth.login.title') }}
```

**Translation file before cleanup:**
```php
// resources/lang/en/auth.php
return [
    'login' => ['title' => 'Login'],
    'old_feature' => 'This is no longer used',  // ← Will be removed
];
```

**After running `--remove-missing`:**
```php
// resources/lang/en/auth.php
return [
    'login' => ['title' => 'Login'],  // ← Preserved
    // 'old_feature' removed automatically
];
```

### Safety Features

- ✅ **Dry Run**: Use `--dry-run` to preview changes before applying
- ✅ **Backups**: Use `--backup` to create timestamped backups
- ✅ **Incremental**: Only removes unused keys, preserves translations
- ✅ **Multi-format**: Works with both PHP and JSON translation files

## 🤖 AI Translation Features

### Supported AI Providers

- **OpenAI**: GPT-3.5 Turbo, GPT-4, and other models
- **Anthropic Claude**: Claude 3 Sonnet, Haiku, and Opus models
- **Google Translate**: Google Cloud Translation API
- **Azure Translator**: Microsoft Azure Translator Text API

### Translation Context

Provide context to improve AI translations:

```php
// config/localizator.php
'ai' => [
    'context' => [
        'domain' => 'e-commerce',      // Business domain
        'tone' => 'friendly',          // Communication tone
        'additional_context' => 'This is a Laravel application for online shopping',
    ],
],
```

### Batch Processing

AI translations are processed in batches to optimize API usage:

```php
'ai' => [
    'batch_size' => 50,          // Translations per batch
    'rate_limit' => 60,          // Requests per minute
],
```

### Validation

Automatic validation ensures translation quality:

- Placeholder preservation (`:name`, `{count}`, etc.)
- Length validation
- Key naming convention checks

## 📁 Output Formats

### JSON Format
```json
{
    "welcome.message": "Welcome to our application",
    "auth.failed": "These credentials do not match our records",
    "dashboard.title": "Dashboard"
}
```

### PHP Array Format

#### Nested Structure (Default)
With `'nested' => true` in configuration, translation keys use dot notation to create organized, nested file structures:

```php
// For keys like 'auth.login.title', 'auth.login.button'
// File: resources/lang/en/auth.php
return [
    'login' => [
        'title' => 'Login',
        'button' => 'Sign In',
        'failed' => 'These credentials do not match our records.',
    ],
    'register' => [
        'title' => 'Create Account',
        'button' => 'Sign Up',
    ],
    'logout' => 'Sign Out', // Single-level key
];

// For keys like 'validation.custom.email.required'
// File: resources/lang/en/validation.php
return [
    'custom' => [
        'email' => [
            'required' => 'Email is required',
            'email' => 'Must be a valid email',
        ],
        'password' => [
            'min' => 'Password must be at least 8 characters',
        ],
    ],
    'attributes' => [
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
    ],
];
```

#### Flat Structure (Legacy)
With `'nested' => false`, keys remain flat within files:

```php
// File: resources/lang/en/auth.php
return [
    'login.title' => 'Login',
    'login.button' => 'Sign In',
    'register.title' => 'Create Account',
];
```

## 🧪 Testing

Run the package tests:

```bash
composer test
```

Run static analysis:

```bash
composer analyse
```

Fix code style:

```bash
composer format
```

Run all quality checks:

```bash
composer test && composer analyse && composer format
```

## 📈 Performance

The package is optimized for large codebases:

- **Efficient File Scanning**: Uses Symfony Finder for fast directory traversal
- **Regex Optimization**: Compiled patterns for translation function detection
- **Memory Management**: Processes files in chunks to handle large projects
- **Caching**: Optional caching of scan results to speed up subsequent runs

## 🛡️ Security

- Validates all translation keys against configurable patterns
- Sanitizes file paths and prevents directory traversal
- Respects Laravel's security practices for file operations
- API keys are stored securely in environment variables

## 🔧 Advanced Configuration

### Custom Translation Functions

Add your own translation function patterns:

```php
'functions' => [
    '__',
    'trans',
    'myCustomTransFunction',  // Your custom function
    'MyClass::translate',     // Static method
],
```

### File Exclusion

Exclude specific files or directories:

```php
'exclude' => [
    'vendor',
    'node_modules',
    'storage',
    'specific-file.php',
    'temp-directory',
],
```

### Custom Output Settings

Customize the generated files:

```php
'output' => [
    'indent' => 2,              // Spaces for indentation
    'line_length' => 80,        // Maximum line length
    'comments' => true,         // Include generation comments
    'backup' => true,           // Create backups
],
```

## 🚨 Troubleshooting

### Common Issues

**1. No translation keys found**
- Check that your `functions` configuration includes all translation methods used in your codebase
- Verify that the `dirs` and `patterns` settings cover your file locations
- Ensure keys aren't all commented out (v1.4.0+ automatically skips commented keys)
- Use `--dry-run -v` to see detailed scanning information

**2. AI translation failures**
- Verify your API keys are correctly set in `.env`
- Check your API quotas and rate limits
- Use smaller batch sizes if encountering timeout errors

**3. --remove-missing not working**
- Ensure the command completed successfully (exit code 0)
- Check that translation keys were actually removed from your source code
- Use `--dry-run` to see what keys would be removed before running
- Verify you're scanning the correct directories with `--dry-run -v`

**4. Permission errors**
- Ensure Laravel has write permissions to the `resources/lang` directory
- Check file ownership and permissions

### Debug Mode

Enable verbose output for debugging:

```bash
php artisan localizator:scan en -v
```

### Log Analysis

Check Laravel logs for detailed error information:

```bash
tail -f storage/logs/laravel.log
```

## 🤝 Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

### Development Setup

1. Clone the repository
2. Install dependencies: `composer install`
3. Run tests: `composer test`
4. Run static analysis: `composer analyse`
5. Fix code style: `composer format`

## 📄 Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for more information on what has changed recently.

## 🔒 Security

If you discover any security-related issues, please email security@syriable.com instead of using the issue tracker.

## 📝 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## 🙏 Credits

- [Syriable Team](https://github.com/syriable)
- [All Contributors](../../contributors)
- Inspired by [amiranagram/localizator](https://github.com/amiranagram/localizator)

## 🔗 Related Packages

- [Laravel Localization](https://github.com/mcamara/laravel-localization)
- [Laravel Translation Manager](https://github.com/barryvdh/laravel-translation-manager)
- [Laravel Lang](https://github.com/Laravel-Lang/lang)

## 💡 Tips & Best Practices

### 1. Organize Translation Keys

Use dot notation for hierarchical organization:

```php
__('dashboard.widgets.sales.title')
__('forms.validation.required')
__('emails.welcome.subject')
```

### 2. Use Placeholders

Keep translations flexible with placeholders:

```php
__('welcome.greeting', ['name' => $user->name])
__('items.count', ['count' => $itemCount])
```

### 3. Review AI Translations

Always review AI-generated translations for:
- Cultural appropriateness
- Context accuracy
- Technical terminology
- Brand consistency

### 4. Version Control

Include translation files in version control but consider:
- Using separate branches for translation updates
- Implementing review processes for translation changes
- Automated testing for translation completeness

### 5. Performance Optimization

For large applications:
- Use JSON format for better performance
- Enable caching in production
- Consider splitting translations into smaller, feature-specific files

---

Made with ❤️ by [Syriable](https://github.com/syriable)