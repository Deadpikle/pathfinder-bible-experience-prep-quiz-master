<?php

use App\Models\NonBlankableWord;
use App\Models\Util;
use PHPUnit\Framework\TestCase;

final class NonBlankableWordTest extends TestCase
{
    public function testSimpleWord(): void
    {
        $nonBlankableWords = [];
        $output = NonBlankableWord::generateFillInQuestion('Hello', 1, $nonBlankableWords);
        $this->assertEquals(1, $output['blank-count']);
        $this->assertTrue($output['data'][0]['shouldBeBlanked']);
        $this->assertTrue($output['data'][0]['blankable']);
        $this->assertEquals('', $output['data'][0]['before']);
        $this->assertEquals('Hello', $output['data'][0]['word']);
        $this->assertEquals('', $output['data'][0]['after']);
    }

    public function testSimpleWordPunctuation(): void
    {
        $nonBlankableWords = [];
        $output = NonBlankableWord::generateFillInQuestion('Hello.', 1, $nonBlankableWords);
        $this->assertEquals(1, $output['blank-count']);
        $this->assertTrue($output['data'][0]['shouldBeBlanked']);
        $this->assertTrue($output['data'][0]['blankable']);
        $this->assertEquals('', $output['data'][0]['before']);
        $this->assertEquals('Hello', $output['data'][0]['word']);
        $this->assertEquals('.', $output['data'][0]['after']);

        $output = NonBlankableWord::generateFillInQuestion('Hello?', 1, $nonBlankableWords);
        $this->assertEquals(1, $output['blank-count']);
        $this->assertTrue($output['data'][0]['shouldBeBlanked']);
        $this->assertTrue($output['data'][0]['blankable']);
        $this->assertEquals('', $output['data'][0]['before']);
        $this->assertEquals('Hello', $output['data'][0]['word']);
        $this->assertEquals('?', $output['data'][0]['after']);

        $output = NonBlankableWord::generateFillInQuestion('Hello!', 1, $nonBlankableWords);
        $this->assertEquals(1, $output['blank-count']);
        $this->assertTrue($output['data'][0]['shouldBeBlanked']);
        $this->assertTrue($output['data'][0]['blankable']);
        $this->assertEquals('', $output['data'][0]['before']);
        $this->assertEquals('Hello', $output['data'][0]['word']);
        $this->assertEquals('!', $output['data'][0]['after']);

        $output = NonBlankableWord::generateFillInQuestion('"Hello."', 1, $nonBlankableWords);
        $this->assertEquals(1, $output['blank-count']);
        $this->assertTrue($output['data'][0]['shouldBeBlanked']);
        $this->assertTrue($output['data'][0]['blankable']);
        $this->assertEquals('"', $output['data'][0]['before']);
        $this->assertEquals('Hello', $output['data'][0]['word']);
        $this->assertEquals('."', $output['data'][0]['after']);
    }

    public function testMultiplePunctuation(): void
    {
        $nonBlankableWords = [];
        $output = NonBlankableWord::generateFillInQuestion('!!!Hello!!??!!..""', 1, $nonBlankableWords);
        $this->assertEquals(1, $output['blank-count']);
        $this->assertTrue($output['data'][0]['shouldBeBlanked']);
        $this->assertTrue($output['data'][0]['blankable']);
        $this->assertEquals('!!!', $output['data'][0]['before']);
        $this->assertEquals('Hello', $output['data'][0]['word']);
        $this->assertEquals('!!??!!..""', $output['data'][0]['after']);
    }

    public function testSpecialPunctuation(): void
    {
        $nonBlankableWords = [];
        $output = NonBlankableWord::generateFillInQuestion('¿Hello«»', 1, $nonBlankableWords);
        $this->assertEquals(1, $output['blank-count']);
        $this->assertTrue($output['data'][0]['shouldBeBlanked']);
        $this->assertTrue($output['data'][0]['blankable']);
        $this->assertEquals('¿', $output['data'][0]['before']);
        $this->assertEquals('Hello', $output['data'][0]['word']);
        $this->assertEquals('«»', $output['data'][0]['after']);
    }

    public function testMultipleWords(): void
    {
        $nonBlankableWords = [];
        $output = NonBlankableWord::generateFillInQuestion('"Hello world!"', 1, $nonBlankableWords);
        $this->assertEquals(2, $output['blank-count']);
        $this->assertTrue($output['data'][0]['shouldBeBlanked']);
        $this->assertTrue($output['data'][0]['blankable']);
        $this->assertEquals('"', $output['data'][0]['before']);
        $this->assertEquals('Hello', $output['data'][0]['word']);
        $this->assertEquals('', $output['data'][0]['after']);
        $this->assertTrue($output['data'][1]['shouldBeBlanked']);
        $this->assertTrue($output['data'][1]['blankable']);
        $this->assertEquals('', $output['data'][1]['before']);
        $this->assertEquals('world', $output['data'][1]['word']);
        $this->assertEquals('!"', $output['data'][1]['after']);
    }

    public function testSkippableWords(): void
    {
        $nonBlankableWords = [new NonBlankableWord(-1, 'and')];
        $output = NonBlankableWord::generateFillInQuestion('Fred and Harry.', 1, $nonBlankableWords);
        $this->assertEquals(2, $output['blank-count']);
        $this->assertTrue($output['data'][0]['shouldBeBlanked']);
        $this->assertTrue($output['data'][0]['blankable']);
        $this->assertEquals('', $output['data'][0]['before']);
        $this->assertEquals('Fred', $output['data'][0]['word']);
        $this->assertEquals('', $output['data'][0]['after']);

        $this->assertFalse($output['data'][1]['shouldBeBlanked']);
        $this->assertFalse($output['data'][1]['blankable']);
        $this->assertEquals('', $output['data'][1]['before']);
        $this->assertEquals('and', $output['data'][1]['word']);
        $this->assertEquals('', $output['data'][1]['after']);
        
        $this->assertTrue($output['data'][2]['shouldBeBlanked']);
        $this->assertTrue($output['data'][2]['blankable']);
        $this->assertEquals('', $output['data'][2]['before']);
        $this->assertEquals('Harry', $output['data'][2]['word']);
        $this->assertEquals('.', $output['data'][2]['after']);
        //
        $nonBlankableWords = [
            new NonBlankableWord(-1, 'and'),
            new NonBlankableWord(-1, 'is'),
            new NonBlankableWord(-1, 'a'),
        ];
        $output = NonBlankableWord::generateFillInQuestion('This is a cool pen.', 1, $nonBlankableWords);
        $this->assertEquals(3, $output['blank-count']);
        $this->assertTrue($output['data'][0]['shouldBeBlanked']);
        $this->assertTrue($output['data'][0]['blankable']);
        $this->assertEquals('', $output['data'][0]['before']);
        $this->assertEquals('This', $output['data'][0]['word']);
        $this->assertEquals('', $output['data'][0]['after']);

        $this->assertFalse($output['data'][1]['shouldBeBlanked']);
        $this->assertFalse($output['data'][1]['blankable']);
        $this->assertEquals('', $output['data'][1]['before']);
        $this->assertEquals('is', $output['data'][1]['word']);
        $this->assertEquals('', $output['data'][1]['after']);

        $this->assertFalse($output['data'][2]['shouldBeBlanked']);
        $this->assertFalse($output['data'][2]['blankable']);
        $this->assertEquals('', $output['data'][2]['before']);
        $this->assertEquals('a', $output['data'][2]['word']);
        $this->assertEquals('', $output['data'][2]['after']);
        
        $this->assertTrue($output['data'][3]['shouldBeBlanked']);
        $this->assertTrue($output['data'][3]['blankable']);
        $this->assertEquals('', $output['data'][3]['before']);
        $this->assertEquals('cool', $output['data'][3]['word']);
        $this->assertEquals('', $output['data'][3]['after']);
        
        $this->assertTrue($output['data'][4]['shouldBeBlanked']);
        $this->assertTrue($output['data'][4]['blankable']);
        $this->assertEquals('', $output['data'][4]['before']);
        $this->assertEquals('pen', $output['data'][4]['word']);
        $this->assertEquals('.', $output['data'][4]['after']);
    }
}
