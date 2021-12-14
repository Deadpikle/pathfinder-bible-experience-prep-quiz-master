<?php

use App\Models\Util;
use PHPUnit\Framework\TestCase;

final class UtilTest extends TestCase
{
    public function testQuestionMarkAdding(): void
    {
        $output = Util::fixQuestionMarkOnQuestion('');
        $this->assertEquals('', $output);
        $output = Util::fixQuestionMarkOnQuestion('h');
        $this->assertEquals('h?', $output);
        $output = Util::fixQuestionMarkOnQuestion('How are you?');
        $this->assertEquals('How are you?', $output);
        $output = Util::fixQuestionMarkOnQuestion('How are you');
        $this->assertEquals('How are you?', $output);
        $output = Util::fixQuestionMarkOnQuestion('How are you.');
        $this->assertEquals('How are you?', $output);
        $output = Util::fixQuestionMarkOnQuestion('How are you!');
        $this->assertEquals('How are you?', $output);
        $output = Util::fixQuestionMarkOnQuestion('According to bob,');
        $this->assertEquals('According to bob?', $output);

        $output = Util::fixQuestionMarkOnQuestion('How are you? Be specific.');
        $this->assertEquals('How are you? Be specific.', $output);
        $output = Util::fixQuestionMarkOnQuestion('How are you? Be specific');
        $this->assertEquals('How are you? Be specific.', $output);
        $output = Util::fixQuestionMarkOnQuestion('How are you? Be specifi');
        $this->assertEquals('How are you? Be specifi?', $output);
        $output = Util::fixQuestionMarkOnQuestion('How are you? specific');
        $this->assertEquals('How are you? specific.', $output);
    }
}
