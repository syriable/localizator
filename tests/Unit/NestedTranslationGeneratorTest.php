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
