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

    #[Test]
    public function it_skips_translation_keys_in_c_style_comments(): void
    {
        $content = '
            {{ __("active.key") }}
            /* {{ __("commented.key") }} */
            {{ __("another.active.key") }}
        ';

        $keys = $this->scanner->findTranslationFunction($content);

        $this->assertContains('active.key', $keys);
        $this->assertContains('another.active.key', $keys);
        $this->assertNotContains('commented.key', $keys);
    }

    #[Test]
    public function it_skips_translation_keys_in_single_line_comments(): void
    {
        $content = '
            {{ __("active.key") }}
            // {{ __("commented.key") }}
            {{ __("another.active.key") }}
        ';

        $keys = $this->scanner->findTranslationFunction($content);

        $this->assertContains('active.key', $keys);
        $this->assertContains('another.active.key', $keys);
        $this->assertNotContains('commented.key', $keys);
    }

    #[Test]
    public function it_skips_translation_keys_in_blade_comments(): void
    {
        $content = '
            {{ __("active.key") }}
            {{-- {{ __("blade.commented.key") }} --}}
            {{ __("another.active.key") }}
        ';

        $keys = $this->scanner->findTranslationFunction($content);

        $this->assertContains('active.key', $keys);
        $this->assertContains('another.active.key', $keys);
        $this->assertNotContains('blade.commented.key', $keys);
    }

    #[Test]
    public function it_skips_translation_keys_in_html_comments(): void
    {
        $content = '
            {{ __("active.key") }}
            <!-- {{ __("html.commented.key") }} -->
            {{ __("another.active.key") }}
        ';

        $keys = $this->scanner->findTranslationFunction($content);

        $this->assertContains('active.key', $keys);
        $this->assertContains('another.active.key', $keys);
        $this->assertNotContains('html.commented.key', $keys);
    }

    #[Test]
    public function it_skips_multiple_commented_translation_keys(): void
    {
        $content = '
            {{ __("active.key1") }}
            /* {{ __("c.comment.key") }} */
            // {{ __("cpp.comment.key") }}
            {{-- {{ __("blade.comment.key") }} --}}
            <!-- {{ __("html.comment.key") }} -->
            {{ __("active.key2") }}
        ';

        $keys = $this->scanner->findTranslationFunction($content);

        // Should include active keys
        $this->assertContains('active.key1', $keys);
        $this->assertContains('active.key2', $keys);
        
        // Should skip all commented keys
        $this->assertNotContains('c.comment.key', $keys);
        $this->assertNotContains('cpp.comment.key', $keys);
        $this->assertNotContains('blade.comment.key', $keys);
        $this->assertNotContains('html.comment.key', $keys);
        
        // Should only have 2 keys total
        $this->assertCount(2, $keys);
    }

    #[Test]
    public function it_handles_multiline_commented_translations(): void
    {
        $content = '
            {{ __("active.before") }}
            /*
                {{ __("multiline.comment.key1") }}
                {{ __("multiline.comment.key2") }}
            */
            {{--
                {{ __("blade.multiline.key1") }}
                {{ __("blade.multiline.key2") }}
            --}}
            {{ __("active.after") }}
        ';

        $keys = $this->scanner->findTranslationFunction($content);

        $this->assertContains('active.before', $keys);
        $this->assertContains('active.after', $keys);
        $this->assertNotContains('multiline.comment.key1', $keys);
        $this->assertNotContains('multiline.comment.key2', $keys);
        $this->assertNotContains('blade.multiline.key1', $keys);
        $this->assertNotContains('blade.multiline.key2', $keys);
        $this->assertCount(2, $keys);
    }

    #[Test]
    public function it_handles_mixed_functions_with_comments(): void
    {
        $content = '
            echo __("php.active");
            /* echo __("php.commented"); */
            @lang("blade.active")
            {{-- @lang("blade.commented") --}}
            {{ $t("vue.active") }}
            // {{ $t("vue.commented") }}
        ';

        $keys = $this->scanner->findTranslationFunction($content);

        $this->assertContains('php.active', $keys);
        $this->assertContains('blade.active', $keys);
        $this->assertContains('vue.active', $keys);
        $this->assertNotContains('php.commented', $keys);
        $this->assertNotContains('blade.commented', $keys);
        $this->assertNotContains('vue.commented', $keys);
        $this->assertCount(3, $keys);
    }
}
