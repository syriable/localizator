# Contributing to Syriable Localizator

Thank you for considering contributing to Syriable Localizator! We welcome contributions from the community and are grateful for any help you can provide.

## Code of Conduct

By participating in this project, you are expected to uphold our Code of Conduct. Please be respectful and professional in all interactions.

## How to Contribute

### Reporting Bugs

If you find a bug, please create an issue on GitHub with the following information:

- A clear and descriptive title
- Steps to reproduce the bug
- Expected behavior
- Actual behavior
- Environment details (PHP version, Laravel version, OS)
- Any relevant error messages or logs

### Suggesting Features

We welcome feature suggestions! Please create an issue on GitHub with:

- A clear and descriptive title
- Detailed description of the proposed feature
- Use cases and benefits
- Any relevant examples or mockups

### Pull Requests

1. **Fork the repository** and create your branch from `main`
2. **Install dependencies**: `composer install`
3. **Make your changes** following our coding standards
4. **Add tests** for any new functionality
5. **Run the test suite**: `composer test`
6. **Run static analysis**: `composer analyse`
7. **Fix code style**: `composer format`
8. **Ensure all checks pass**
9. **Create a pull request** with a clear description of changes

## Development Setup

### Prerequisites

- PHP 8.3 or higher
- Composer
- Git

### Installation

1. Fork the repository on GitHub
2. Clone your fork locally:
   ```bash
   git clone https://github.com/your-username/localizator.git
   cd localizator
   ```
3. Install dependencies:
   ```bash
   composer install
   ```

### Running Tests

Run the full test suite:

```bash
composer test
```

Run specific test types:

```bash
# Unit tests only
./vendor/bin/phpunit --testsuite=Unit

# Feature tests only
./vendor/bin/phpunit --testsuite=Feature

# With coverage
composer test-coverage
```

### Code Quality

We maintain high code quality standards. Please ensure your contributions meet these standards:

#### Static Analysis

Run PHPStan to check for type errors:

```bash
composer analyse
```

#### Code Formatting

Use Laravel Pint to format your code:

```bash
composer format
```

#### All Quality Checks

Run all quality checks at once:

```bash
composer test && composer analyse && composer format
```

## Coding Standards

### PHP Standards

- Follow PSR-12 coding standards
- Use strict typing: `declare(strict_types=1)`
- Type hint all method parameters and return types
- Use meaningful variable and method names
- Write self-documenting code with minimal comments

### Documentation

- Update README.md for any user-facing changes
- Add PHPDoc blocks for complex methods
- Include usage examples for new features
- Update CHANGELOG.md following the format

### Testing

- Write unit tests for all new classes and methods
- Write feature tests for CLI commands and integrations
- Achieve at least 90% code coverage for new code
- Use descriptive test method names
- Follow the Arrange-Act-Assert pattern

#### Test Examples

```php
#[Test]
public function it_can_extract_translation_keys_from_php_files(): void
{
    // Arrange
    $content = '<?php echo __("Hello World"); ?>';
    $scanner = new FileScannerService();
    
    // Act
    $keys = $scanner->findTranslationFunction($content);
    
    // Assert
    $this->assertContains('Hello World', $keys);
}
```

### Git Workflow

1. **Branching**: Create feature branches from `main`
   ```bash
   git checkout -b feature/add-new-provider
   ```

2. **Commits**: Use descriptive commit messages
   ```bash
   git commit -m "Add support for DeepL translation provider"
   ```

3. **Pull Requests**: Include clear descriptions and link issues
   - Explain what the change does
   - Why the change is necessary
   - How to test the change

### File Structure

Follow this structure for new features:

```
src/
├── Commands/          # Artisan commands
├── Contracts/         # Interfaces
├── Services/          # Business logic
└── Support/           # Helper classes

tests/
├── Feature/           # Integration tests
├── Unit/              # Unit tests
└── fixtures/          # Test data
```

## Types of Contributions

### Bug Fixes

- Always include tests that verify the fix
- Reference the issue number in the commit message
- Ensure the fix doesn't break existing functionality

### New Features

- Discuss major features in an issue first
- Follow existing patterns and conventions
- Include comprehensive tests
- Update documentation

### Performance Improvements

- Include benchmarks if possible
- Ensure changes don't affect functionality
- Document performance gains

### Documentation

- Fix typos and improve clarity
- Add missing documentation
- Update examples to match current APIs

## AI Provider Integration

When adding new AI providers:

1. Create a new method in `AITranslationService`
2. Add configuration in `config/localizator.php`
3. Add language code mappings if needed
4. Include comprehensive tests
5. Update documentation with configuration examples

### Example Provider Addition

```php
private function translateWithNewProvider(array $texts, string $sourceLanguage, string $targetLanguage): array
{
    $apiKey = $this->config['api_key'] ?? null;
    if (!$apiKey) {
        throw new \Exception('New Provider API key not configured');
    }

    // Implementation here

    return $translations;
}
```

## Release Process

Releases are handled by maintainers. Contributors should:

1. Update CHANGELOG.md with their changes
2. Ensure version compatibility is maintained
3. Update documentation as needed

## Questions?

If you have questions about contributing:

- Check existing issues and discussions
- Create a new issue for specific questions
- Reach out to maintainers if needed

## Recognition

Contributors will be recognized in:

- CHANGELOG.md for their contributions
- README.md credits section
- GitHub contributors list

Thank you for contributing to Syriable Localizator! 🎉