<?php

use App\Helpers\SafeMarkdown;
use PHPUnit\Framework\TestCase;

final class SafeMarkdownTest extends TestCase
{
    public function testRendersConservativeMarkdownSubset(): void
    {
        $html = SafeMarkdown::render("# Welcome\n\n- **One**\n- *Two*\n\n`code`");
        $this->assertStringContainsString('<h1>Welcome</h1>', $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li><strong>One</strong></li>', $html);
        $this->assertStringContainsString('<li><em>Two</em></li>', $html);
        $this->assertStringContainsString('<code>code</code>', $html);
    }

    public function testEscapesRawHtmlAndRejectsUnsafeLinks(): void
    {
        $html = SafeMarkdown::render('<script>alert(1)</script> [click](javascript:alert(1))');
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('href=', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function testAllowsSafeExternalAndLocalLinks(): void
    {
        $html = SafeMarkdown::render('[External](https://example.com/?a=1&b=2) [Local](/settings)');
        $this->assertStringContainsString('href="https://example.com/?a=1&amp;b=2"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('href="/settings"', $html);
    }
}
