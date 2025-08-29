<?php

declare(strict_types=1);

namespace Syriable\Localizator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Syriable\Localizator\Services\FileScannerService;
use Syriable\Localizator\Tests\TestCase;

class FileScannerServiceTest extends TestCase
{
    private FileScannerService $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = new FileScannerService;
    }

    #[Test]
    public function it_can_extract_php_function_translation_keys(): void
    {
        $content = '<?php echo __("Hello World"); echo trans("welcome.message"); ?>';

        $keys = $this->scanner->findTranslationFunction($content);

        $this->assertContains('Hello World', $keys);
        $this->assertContains('welcome.message', $keys);
    }

    #[Test]
    public function it_can_extract_blade_directive_translation_keys(): void
    {
        $content = '@lang("auth.failed") @choice("messages.items", $count)';

        $keys = $this->scanner->findTranslationFunction($content);

        $this->assertContains('auth.failed', $keys);
        $this->assertContains('messages.items', $keys);
    }

    #[Test]
    public function it_can_extract_vue_i18n_translation_keys(): void
    {
        $content = '{{ $t("dashboard.title") }} <span>{{ $tc("users.count", userCount) }}</span>';

        $keys = $this->scanner->findTranslationFunction($content);

        $this->assertContains('dashboard.title', $keys);
        $this->assertContains('users.count', $keys);
    }

    #[Test]
    public function it_ignores_variables_as_translation_keys(): void
    {
        $content = '<?php echo __($variable); echo trans("{$dynamic}"); ?>';

        $keys = $this->scanner->findTranslationFunction($content);

        $this->assertEmpty($keys);
    }

    #[Test]
    public function it_handles_escaped_quotes_in_translation_keys(): void
    {
        $content = '<?php echo __("Hello \"World\""); echo trans(\'It\\\'s working\'); ?>';

        $keys = $this->scanner->findTranslationFunction($content);

        $this->assertContains('Hello "World"', $keys);
        $this->assertContains("It's working", $keys);
    }

    #[Test]
    public function it_removes_duplicate_keys(): void
    {
        $content = '<?php echo __("test"); echo __("test"); echo trans("test"); ?>';

        $keys = $this->scanner->findTranslationFunction($content);

        $this->assertCount(1, $keys);
        $this->assertContains('test', $keys);
    }

    #[Test]
    public function it_returns_empty_array_for_non_existent_file(): void
    {
        $keys = $this->scanner->extractTranslationKeys('/non/existent/file.php');

        $this->assertEmpty($keys);
    }
}
