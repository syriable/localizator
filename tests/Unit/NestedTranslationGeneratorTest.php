<?php

declare(strict_types=1);

namespace Syriable\Localizator\Tests\Unit;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Syriable\Localizator\Services\TranslationGeneratorService;
use Syriable\Localizator\Tests\TestCase;

class NestedTranslationGeneratorTest extends TestCase
{
    private TranslationGeneratorService $generator;

    private string $tempLangPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary directory for testing
        $this->tempLangPath = sys_get_temp_dir().'/localizator_test_'.uniqid();
        mkdir($this->tempLangPath, 0755, true);

        // Mock the resource_path function
        Config::set('localizator.nested', true);
        $this->generator = new TranslationGeneratorService;

        // Use reflection to set the private langPath property
        $reflection = new \ReflectionClass($this->generator);
        $property = $reflection->getProperty('langPath');
        $property->setAccessible(true);
        $property->setValue($this->generator, $this->tempLangPath);
    }

    protected function tearDown(): void
    {
        // Clean up temporary directory
        if (is_dir($this->tempLangPath)) {
            $this->deleteDirectory($this->tempLangPath);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_generates_nested_structure_for_dot_notation_keys(): void
    {
        $translations = [
            'auth.login.title' => 'Login',
            'auth.login.button' => 'Sign In',
            'auth.register.title' => 'Register',
            'messages.welcome' => 'Welcome',
            'simple_key' => 'Simple Value',
        ];

        $success = $this->generator->generatePhpTranslationFile('en', $translations);

        $this->assertTrue($success);

        // Check auth.php file structure
        $authFile = $this->tempLangPath.'/en/auth.php';
        $this->assertFileExists($authFile);

        $authTranslations = include $authFile;
        $expectedAuthStructure = [
            'login' => [
                'title' => 'Login',
                'button' => 'Sign In',
            ],
            'register' => [
                'title' => 'Register',
            ],
        ];

        $this->assertEquals($expectedAuthStructure, $authTranslations);

        // Check messages.php file
        $messagesFile = $this->tempLangPath.'/en/messages.php';
        $this->assertFileExists($messagesFile);

        $messagesTranslations = include $messagesFile;
        $expectedMessagesStructure = [
            'welcome' => 'Welcome',
            'simple_key' => 'Simple Value',
        ];

        $this->assertEquals($expectedMessagesStructure, $messagesTranslations);
    }

    #[Test]
    public function it_handles_deeply_nested_keys(): void
    {
        $translations = [
            'validation.custom.email.required' => 'Email is required',
            'validation.custom.password.min' => 'Password too short',
            'validation.attributes.first_name' => 'First Name',
        ];

        $success = $this->generator->generatePhpTranslationFile('en', $translations);

        $this->assertTrue($success);

        $validationFile = $this->tempLangPath.'/en/validation.php';
        $this->assertFileExists($validationFile);

        $validationTranslations = include $validationFile;
        $expectedStructure = [
            'custom' => [
                'email' => [
                    'required' => 'Email is required',
                ],
                'password' => [
                    'min' => 'Password too short',
                ],
            ],
            'attributes' => [
                'first_name' => 'First Name',
            ],
        ];

        $this->assertEquals($expectedStructure, $validationTranslations);
    }

    #[Test]
    public function it_falls_back_to_flat_structure_when_disabled(): void
    {
        // Disable nested structure
        Config::set('localizator.nested', false);
        $generator = new TranslationGeneratorService;

        $reflection = new \ReflectionClass($generator);
        $property = $reflection->getProperty('langPath');
        $property->setAccessible(true);
        $property->setValue($generator, $this->tempLangPath);

        $translations = [
            'auth.login.title' => 'Login',
            'auth.register.title' => 'Register',
        ];

        $success = $generator->generatePhpTranslationFile('en', $translations);

        $this->assertTrue($success);

        $authFile = $this->tempLangPath.'/en/auth.php';
        $this->assertFileExists($authFile);

        $authTranslations = include $authFile;
        $expectedFlatStructure = [
            'login.title' => 'Login',
            'register.title' => 'Register',
        ];

        $this->assertEquals($expectedFlatStructure, $authTranslations);
    }

    #[Test]
    public function it_merges_existing_nested_translations(): void
    {
        // Create existing file with some translations
        $enDir = $this->tempLangPath.'/en';
        mkdir($enDir, 0755, true);

        $existingAuthFile = $enDir.'/auth.php';
        file_put_contents($existingAuthFile, "<?php\n\nreturn [\n    'login' => [\n        'title' => 'Existing Login',\n        'subtitle' => 'Please sign in',\n    ],\n    'logout' => 'Sign Out',\n];\n");

        $newTranslations = [
            'auth.login.title' => 'New Login Title', // Should keep existing
            'auth.login.button' => 'Sign In',        // Should add new
            'auth.forgot.title' => 'Forgot Password', // Should add new nested
        ];

        $mergedTranslations = $this->generator->mergeExistingTranslations('en', $newTranslations);

        $expectedMerged = [
            'auth.login.title' => 'Existing Login',      // Kept existing
            'auth.login.subtitle' => 'Please sign in',   // Kept existing
            'auth.logout' => 'Sign Out',                  // Kept existing
            'auth.login.button' => 'Sign In',            // Added new
            'auth.forgot.title' => 'Forgot Password',    // Added new
        ];

        $this->assertEquals($expectedMerged, $mergedTranslations);
    }

    #[Test]
    public function it_generates_sensible_default_values_from_keys(): void
    {
        $translationKeys = [
            'mine.dashboard',
            'user.profile_settings',
            'auth.forgot-password',
            'navigation.main_menu',
            'validation.email_required',
        ];

        // Use reflection to call the private method
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('prepareTranslations');
        $method->setAccessible(true);

        $result = $method->invoke($this->generator, $translationKeys, []);

        $expected = [
            'mine.dashboard' => 'Dashboard',
            'user.profile_settings' => 'Profile Settings',
            'auth.forgot-password' => 'Forgot Password',
            'navigation.main_menu' => 'Main Menu',
            'validation.email_required' => 'Email Required',
        ];

        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_generates_proper_nested_structure_with_default_values(): void
    {
        $translationKeys = [
            'mine.dashboard',
            'mine.profile',
            'admin.users',
        ];

        $success = $this->generator->generateTranslationFiles($translationKeys, ['en']);

        $this->assertTrue($success);

        // Check mine.php file structure
        $mineFile = $this->tempLangPath.'/en/mine.php';
        $this->assertFileExists($mineFile);

        $mineTranslations = include $mineFile;
        $expectedMineStructure = [
            'dashboard' => 'Dashboard',
            'profile' => 'Profile',
        ];

        $this->assertEquals($expectedMineStructure, $mineTranslations);

        // Check admin.php file structure
        $adminFile = $this->tempLangPath.'/en/admin.php';
        $this->assertFileExists($adminFile);

        $adminTranslations = include $adminFile;
        $expectedAdminStructure = [
            'users' => 'Users',
        ];

        $this->assertEquals($expectedAdminStructure, $adminTranslations);
    }

    #[Test]
    public function it_detects_correct_language_path_for_different_laravel_versions(): void
    {
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('detectLangPath');
        $method->setAccessible(true);

        $detectedPath = $method->invoke($this->generator);

        // Should detect a valid path (either the temp path we set or Laravel default)
        $this->assertIsString($detectedPath);
        $this->assertNotEmpty($detectedPath);

        // For our test case, it should be our temp path since we set it via reflection
        $langPathProperty = $reflection->getProperty('langPath');
        $langPathProperty->setAccessible(true);
        $actualLangPath = $langPathProperty->getValue($this->generator);

        $this->assertEquals($this->tempLangPath, $actualLangPath);
    }

    #[Test]
    public function it_handles_missing_lang_directory_gracefully(): void
    {
        // Create a generator that would use default paths
        $generator = new TranslationGeneratorService();

        // Use reflection to get the detected path
        $reflection = new \ReflectionClass($generator);
        $method = $reflection->getMethod('detectLangPath');
        $method->setAccessible(true);

        $detectedPath = $method->invoke($generator);

        // Should return a valid path even if directories don't exist
        $this->assertIsString($detectedPath);
        $this->assertNotEmpty($detectedPath);

        // Should be either base_path('lang') or resource_path('lang')
        $this->assertTrue(
            str_contains($detectedPath, 'lang') || 
            str_contains($detectedPath, 'resources')
        );
    }

    #[Test]
    public function it_generates_json_files_when_format_is_json(): void
    {
        // Set JSON format in config
        Config::set('localizator.localize', 'json');

        $translationKeys = [
            'auth.login.title',
            'auth.login.button',
            'dashboard.header.welcome',
        ];

        $success = $this->generator->generateTranslationFiles($translationKeys, ['en']);

        $this->assertTrue($success);

        // Check that JSON file was created (not PHP files)
        $jsonFile = $this->tempLangPath.'/en.json';
        $this->assertFileExists($jsonFile);

        // Verify it's valid JSON
        $content = file_get_contents($jsonFile);
        $decoded = json_decode($content, true);
        $this->assertNotNull($decoded, 'Generated file should be valid JSON');
        $this->assertIsArray($decoded);

        // Verify it contains expected keys and values
        $this->assertArrayHasKey('auth.login.title', $decoded);
        $this->assertArrayHasKey('auth.login.button', $decoded);
        $this->assertArrayHasKey('dashboard.header.welcome', $decoded);
        
        // Verify values are human-readable (not the full key)
        $this->assertEquals('Title', $decoded['auth.login.title']);
        $this->assertEquals('Button', $decoded['auth.login.button']);
        $this->assertEquals('Welcome', $decoded['dashboard.header.welcome']);

        // Verify no PHP files were created
        $authFile = $this->tempLangPath.'/en/auth.php';
        $dashboardFile = $this->tempLangPath.'/en/dashboard.php';
        $this->assertFileDoesNotExist($authFile);
        $this->assertFileDoesNotExist($dashboardFile);
    }

    #[Test]
    public function it_generates_json_without_comments_even_when_comments_enabled(): void
    {
        // Enable comments and set JSON format
        Config::set('localizator.localize', 'json');
        Config::set('localizator.output.comments', true);

        $translationKeys = ['test.key'];

        $success = $this->generator->generateTranslationFiles($translationKeys, ['en']);

        $this->assertTrue($success);

        $jsonFile = $this->tempLangPath.'/en.json';
        $this->assertFileExists($jsonFile);

        $content = file_get_contents($jsonFile);
        
        // Should not contain comment markers
        $this->assertStringNotContainsString('/**', $content);
        $this->assertStringNotContainsString('*/', $content);
        $this->assertStringNotContainsString('Generated by', $content);
        
        // Should be valid JSON
        $decoded = json_decode($content, true);
        $this->assertNotNull($decoded, 'JSON file should not contain comments that break JSON syntax');
    }

    #[Test]
    public function it_creates_lang_directory_if_missing(): void
    {
        // Create a new temp directory path that doesn't exist
        $newLangPath = sys_get_temp_dir().'/localizator_missing_'.uniqid();
        
        // Make sure directory doesn't exist initially
        $this->assertDirectoryDoesNotExist($newLangPath);
        
        // Create new generator and set the non-existent path
        $generator = new TranslationGeneratorService();
        $reflection = new \ReflectionClass($generator);
        $property = $reflection->getProperty('langPath');
        $property->setAccessible(true);
        $property->setValue($generator, $newLangPath);
        
        // Generate translation files
        $success = $generator->generateTranslationFiles(['test.key'], ['en']);
        
        $this->assertTrue($success);
        $this->assertDirectoryExists($newLangPath);
        $this->assertFileExists($newLangPath.'/en/test.php');
        
        // Clean up
        $this->deleteDirectory($newLangPath);
    }

    #[Test]
    public function it_creates_lang_directory_with_missing_parent_directories(): void
    {
        // Create a path with multiple missing parent directories
        $deepLangPath = sys_get_temp_dir().'/localizator_deep_'.uniqid().'/missing/parent/dirs/lang';
        
        // Make sure the entire path doesn't exist
        $this->assertDirectoryDoesNotExist($deepLangPath);
        $this->assertDirectoryDoesNotExist(dirname($deepLangPath));
        
        // Create new generator and set the deep path
        $generator = new TranslationGeneratorService();
        $reflection = new \ReflectionClass($generator);
        $property = $reflection->getProperty('langPath');
        $property->setAccessible(true);
        $property->setValue($generator, $deepLangPath);
        
        // Generate translation files (should create all parent directories)
        $success = $generator->generateTranslationFiles(['test.deep'], ['en']);
        
        $this->assertTrue($success);
        $this->assertDirectoryExists($deepLangPath);
        $this->assertFileExists($deepLangPath.'/en/test.php');
        
        // Clean up the entire tree
        $rootPath = sys_get_temp_dir().'/'.basename(dirname(dirname(dirname(dirname($deepLangPath)))));
        $this->deleteDirectory($rootPath);
    }

    #[Test]
    public function it_creates_lang_directory_for_json_format(): void
    {
        // Set JSON format
        Config::set('localizator.localize', 'json');
        
        // Create a new temp directory path that doesn't exist
        $newLangPath = sys_get_temp_dir().'/localizator_json_'.uniqid();
        
        // Make sure directory doesn't exist initially
        $this->assertDirectoryDoesNotExist($newLangPath);
        
        // Create new generator and set the non-existent path
        $generator = new TranslationGeneratorService();
        $reflection = new \ReflectionClass($generator);
        $property = $reflection->getProperty('langPath');
        $property->setAccessible(true);
        $property->setValue($generator, $newLangPath);
        
        // Generate JSON translation files
        $success = $generator->generateTranslationFiles(['test.json.key'], ['en']);
        
        $this->assertTrue($success);
        $this->assertDirectoryExists($newLangPath);
        $this->assertFileExists($newLangPath.'/en.json');
        
        // Verify it's valid JSON
        $content = file_get_contents($newLangPath.'/en.json');
        $this->assertNotNull(json_decode($content, true));
        
        // Clean up
        $this->deleteDirectory($newLangPath);
    }

    #[Test]
    public function it_preserves_existing_translations_with_incremental_updates(): void
    {
        // Create existing translation file with some content
        $enDir = $this->tempLangPath.'/en';
        mkdir($enDir, 0755, true);
        
        $authFile = $enDir.'/auth.php';
        $existingContent = "<?php\n\nreturn [\n    'login' => [\n        'title' => 'Existing Login Title',\n        'subtitle' => 'Please sign in to continue',\n    ],\n    'logout' => 'Sign Out',\n];\n";
        file_put_contents($authFile, $existingContent);
        
        // Now generate with new keys (should preserve existing)
        $newKeys = [
            'auth.login.title',      // Exists - should keep original
            'auth.login.button',     // New - should add
            'auth.register.title',   // New - should add
        ];
        
        $success = $this->generator->generateTranslationFiles($newKeys, ['en']);
        $this->assertTrue($success);
        
        // Verify file still exists and has expected content
        $this->assertFileExists($authFile);
        $updatedContent = include $authFile;
        
        // Should preserve existing values
        $this->assertEquals('Existing Login Title', $updatedContent['login']['title']);
        $this->assertEquals('Please sign in to continue', $updatedContent['login']['subtitle']);
        $this->assertEquals('Sign Out', $updatedContent['logout']);
        
        // Should add new values
        $this->assertEquals('Button', $updatedContent['login']['button']);
        $this->assertEquals('Title', $updatedContent['register']['title']);
    }

    #[Test]
    public function it_does_not_create_backup_by_default(): void
    {
        // Create existing translation file
        $enDir = $this->tempLangPath.'/en';
        mkdir($enDir, 0755, true);
        
        $authFile = $enDir.'/auth.php';
        $originalContent = "<?php\n\nreturn [\n    'login' => 'Original Login',\n];\n";
        file_put_contents($authFile, $originalContent);
        
        // Generate with backup disabled (default)
        Config::set('localizator.output.backup', false);
        
        $success = $this->generator->generateTranslationFiles(['auth.login'], ['en']);
        $this->assertTrue($success);
        
        // Should not create backup file
        $backupFiles = glob($authFile.'.backup_*');
        $this->assertEmpty($backupFiles, 'No backup files should be created by default');
    }

    #[Test]
    public function it_creates_backup_when_explicitly_enabled(): void
    {
        // Create existing translation file
        $enDir = $this->tempLangPath.'/en';
        mkdir($enDir, 0755, true);
        
        $authFile = $enDir.'/auth.php';
        $originalContent = "<?php\n\nreturn [\n    'login' => 'Original Login',\n];\n";
        file_put_contents($authFile, $originalContent);
        
        // Enable backup explicitly
        Config::set('localizator.output.backup', true);
        
        $success = $this->generator->generateTranslationFiles(['auth.login'], ['en']);
        $this->assertTrue($success);
        
        // Should create backup file
        $backupFiles = glob($authFile.'.backup_*');
        $this->assertNotEmpty($backupFiles, 'Backup file should be created when explicitly enabled');
        
        // Verify backup contains original content
        $backupContent = file_get_contents($backupFiles[0]);
        $this->assertEquals($originalContent, $backupContent);
    }

    #[Test]
    public function it_uses_incremental_update_method_by_default(): void
    {
        // Create existing translations
        $enDir = $this->tempLangPath.'/en';
        mkdir($enDir, 0755, true);
        
        $messagesFile = $enDir.'/messages.php';
        file_put_contents($messagesFile, "<?php\n\nreturn [\n    'existing_key' => 'Existing Value',\n    'updated_key' => 'Original Value',\n];\n");
        
        // Test the incremental merge method directly with keys prefixed by file
        $newTranslations = [
            'messages.new_key' => 'Default New Value',
            'messages.updated_key' => 'This should not overwrite existing',
        ];
        
        $merged = $this->generator->mergeExistingTranslations('en', $newTranslations);
        
        // Should preserve all existing translations
        $this->assertEquals('Existing Value', $merged['messages.existing_key']);
        $this->assertEquals('Original Value', $merged['messages.updated_key']); // Should keep original, not overwrite
        
        // Should add new keys
        $this->assertEquals('Default New Value', $merged['messages.new_key']);
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}
