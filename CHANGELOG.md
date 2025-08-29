# Changelog

All notable changes to `syriable/localizator` will be documented in this file.

## [1.4.0] - 2025-08-29

### Added
- **Comment Detection & Skipping**: Automatically skip translation keys found in comments
  - Supports C-style comments (`/* {{ __('key') }} */`)
  - Supports single-line comments (`// {{ __('key') }}`)
  - Supports Blade comments (`{{-- {{ __('key') }} --}}`)
  - Supports HTML comments (`<!-- {{ __('key') }} -->`)
  - Works with multiline comments across all formats
  - Prevents cluttering language files with disabled/temporary keys

### Fixed
- **--remove-missing Option**: Fixed critical issue where `--remove-missing` flag wasn't working properly
  - Now correctly removes unused translation keys from language files
  - Fixed configuration not being applied in scan command
  - Enhanced TranslationGeneratorService to handle remove-missing during incremental updates
  - Added comprehensive integration test to verify end-to-end functionality

### Enhanced
- **FileScannerService**: Added `removeCommentedTranslations()` method with regex patterns for all comment types
- **Test Coverage**: Added 8 comprehensive unit tests for comment-skipping functionality
- **Documentation**: Updated README with detailed examples and troubleshooting for both new features

### Technical
- Enhanced `LocalizatorScanCommand.php` with proper config setting for remove-missing option
- Improved `TranslationGeneratorService.php` incremental update logic
- All tests passing (51+ total tests with comprehensive coverage)

## [Unreleased]

### Added
- Initial release of Syriable Localizator
- Comprehensive file scanning for translation functions in PHP, Blade, Vue.js, and JavaScript files
- AI-powered translation support with OpenAI, Claude, Google Translate, and Azure Translator
- Support for both JSON and PHP array translation file formats
- Interactive CLI command with extensive options
- Automatic backup creation before file modifications
- Dry-run capability for safe testing
- Translation key validation and placeholder preservation
- Batch processing for efficient AI translations
- Rate limiting and error handling for AI API calls
- Comprehensive test suite with PHPUnit
- Static analysis with PHPStan level 8
- Code formatting with Laravel Pint
- Full PSR-4 compliance and modern PHP 8.3+ features

### Configuration
- Extensive configuration system with environment variable support
- Customizable file patterns and directory scanning
- Flexible translation function detection
- AI provider configuration with context settings
- Output formatting and validation options

### Testing
- Unit tests for core functionality
- Feature tests for CLI commands
- Test fixtures for realistic scenarios
- Comprehensive coverage of scanning and translation features

### Documentation
- Comprehensive README with usage examples
- Configuration guide with all available options
- Troubleshooting section for common issues
- Best practices and tips for optimal usage