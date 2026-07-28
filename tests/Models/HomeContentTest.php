<?php

use App\Models\HomeContent;
use PHPUnit\Framework\TestCase;

final class HomeContentTest extends TestCase
{
    public function testRenderSubstitutesWhitelistedValuesBeforeEscaping(): void
    {
        $content = new HomeContent();
        $content->markdown = "## :year\n\n**:fillInChapters**";
        $html = $content->render([
            'year' => 2027,
            'fillInChapters' => '<script>bad()</script>',
        ]);

        $this->assertStringContainsString('<h2>2027</h2>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;bad()&lt;/script&gt;', $html);
    }

    public function testDefaultEnglishMarkdownIsAvailable(): void
    {
        $markdown = HomeContent::defaultEnglishMarkdown();
        $this->assertNotSame('', $markdown);
        $this->assertStringContainsString('## Resources', $markdown);
    }

    public function testDefaultGlobalContentIsPrependedWhenOnlyConferenceContentExists(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite is not available.');
        }
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('
            CREATE TABLE HomeContents (
                HomeContentID INTEGER PRIMARY KEY,
                YearID INTEGER,
                ConferenceID INTEGER,
                UILanguageID INTEGER,
                Markdown TEXT,
                UpdatedByUserID INTEGER,
                DateUpdated TEXT
            );
            INSERT INTO HomeContents VALUES
                (1, 9, 22, 1, "## Conference addition", NULL, "2026-07-28 12:00:00");
        ');

        $contents = HomeContent::loadForHome(9, 22, 11, 1, 1, $db);

        $this->assertCount(2, $contents);
        $this->assertTrue($contents[0]->isGlobal);
        $this->assertStringContainsString('## Resources', $contents[0]->markdown);
        $this->assertFalse($contents[1]->isGlobal);
        $this->assertSame('## Conference addition', $contents[1]->markdown);
    }
}
