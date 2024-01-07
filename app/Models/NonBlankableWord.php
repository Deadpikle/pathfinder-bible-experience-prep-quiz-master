<?php

namespace App\Models;

use PDO;

class NonBlankableWord
{
    public $wordID;
    public $word;

    public function __construct(int $wordID, string $word)
    {
        $this->wordID = $wordID;
        $this->word = $word;
    }

    private static function loadWords(string $whereClause, array $whereParams, PDO $db) : array
    {
        $query = '
            SELECT WordID, Word
            FROM BlankableWords 
            ' . $whereClause . '
            ORDER BY Word';
        $stmt = $db->prepare($query);
        $stmt->execute($whereParams);
        $words = $stmt->fetchAll();
        $output = [];
        foreach ($words as $row) {
            $output[] = new NonBlankableWord($row['WordID'], $row['Word']);
        }
        return $output;
    }

    public static function loadAllBlankableWords(PDO $db) : array
    {
        return NonBlankableWord::loadWords('', [], $db);
    }

    public static function loadNonBlankableWordByID(int $nonBlankableWordID, PDO $db) : ?NonBlankableWord
    {
        $data = NonBlankableWord::loadWords(' WHERE WordID = ? ', [ $nonBlankableWordID ], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    public static function loadNonBlankableWordByWord(string $word, PDO $db) : ?NonBlankableWord
    {
        $data = NonBlankableWord::loadWords(' WHERE Word = ? ', [ $word ], $db);
        return count($data) > 0 ? $data[0] : null;
    }

    public function create(PDO $db)
    {
        $query = 'INSERT INTO BlankableWords (Word) VALUES (?)';
        $stmt = $db->prepare($query);
        $stmt->execute([ $this->word ]);
        $this->wordID = $db->lastInsertId();
    }

    public function update(PDO $db)
    {
        $query = 'UPDATE BlankableWords SET Word = ? WHERE WordID = ?';
        $stmt = $db->prepare($query);
        $stmt->execute([ $this->word, $this->wordID ]);
    }

    public function delete(PDO $db)
    {
        $query = 'DELETE FROM BlankableWords WHERE WordID = ?';
        $stmt = $db->prepare($query);
        $stmt->execute([ $this->wordID ]);
    }

	const SKIPPABLE = ['a', 'is', 'and', 'or', 'but', 'the', '...'];
    const PUNCTUATION = ['.', '?', '!', ',', ' ', '¿', '«', '»'];
    const DEBUG = false;

    // $percentToBlank should be a decimal
	public static function generateFillInQuestion($phrase, $percentToBlank, $nonBlankableWords) {
        if (NonBlankableWord::DEBUG) {
            echo '<br>-----<br>';
            echo $phrase;
            echo '<br>';
        }
		$tokenized = self::tokenize($phrase, $nonBlankableWords);

        $data = $tokenized["word-data"];
        $blankableIndices = $tokenized["blankable-indices"];
        $numberWords = count($data);
        $numberToBlank = floor($percentToBlank * count($blankableIndices));
        if ($numberToBlank == 0 && $percentToBlank >= 0.0) {
            $numberToBlank = 1;
        }

        // get items we should blank
		shuffle($blankableIndices);
		$blankableIndices = array_slice($blankableIndices, 0, $numberToBlank);
        for ($i = 0; $i < count($blankableIndices); $i++) {
            $data[$blankableIndices[$i]]["shouldBeBlanked"] = true;
        }
        
        if (NonBlankableWord::DEBUG) {
            echo $numberToBlank . ' to blank';
            echo '<br>';
            print_r($data);
            echo '<br>-----<br>';
        }
        return [
            "data" => $data,
            "blank-count" => $numberToBlank
        ];
	}

    private static function tokenize($phrase, $nonBlankableWords) {
        preg_match_all("/[^\s]+/", $phrase, $words);
        $words = $words[0];
        if (NonBlankableWord::DEBUG) {
            print_r($words);
        }
        $adjustedWords = array();
        foreach ($words as $word) {
            if (strpos($word, '...') !== false) {
                $parts = explode("...", $word);
                $i = 0;
                foreach ($parts as $part) {
                    if ($i != count($parts) - 1) { // so if we split more than once we get all the ... in the output :)
                        $part = $part . "...";
                    }
                    $adjustedWords[] = $part;
                    $i++;
                }
            }
            else {
                $adjustedWords[] = $word;
            }
        }
        $words = $adjustedWords;
        $word_arrays = [];
        $blankableIndices = [];
        for ($i = 0; $i < count($words); $i++) {
            $word = $words[$i];
            // could add ^' to keep words like 'taint (as in "'taint so") in the word section and not in the before section
            // preg_match("/^([^\w]*)(.*?)([^\w]*)$/", $word, $matches);
            preg_match("/^([^\w]*)(.*?)([^\w]*)$/u", $word, $matches);
            $actualWord = trim($matches[2]);
            if (NonBlankableWord::DEBUG) {
                echo $actualWord . '<br>';
            }
            $wordToLookFor = strtolower($actualWord);
            $isBlankable = true;
            foreach ($nonBlankableWords as $nonBlankableWord) {
                if ($wordToLookFor === strtolower($nonBlankableWord->word)) {
                    $isBlankable = false;
                    break;
                }
            }
            //$isBlankable = array_search($wordToLookFor, $nonBlankableWords) === false ? true : false;
            if ($isBlankable && !is_numeric($actualWord)) {
                if (NonBlankableWord::DEBUG) {
                    echo '"' . $actualWord . '" is blankable';
                    echo '<br>';
                }
                $blankableIndices[] = $i;
            }
            $word_array = [
                "before" => trim($matches[1]),
                "word" => $actualWord,
                "after" => trim($matches[3]),
                "blankable" => $isBlankable,
                "shouldBeBlanked" => false
            ];
            $word_arrays[] = $word_array;
        }
        return [
            "blankable-indices" => $blankableIndices,
            "word-data" => $word_arrays
        ];
    }
}




	// #generate_question
	/*function test_generate_question() {
		$t = new Test();

		// replaces a word with a blank
		$t->equal(generate_question("Hello", 1), ['_']);

		// doesn't include punctuation in the blank
		$t->equal(generate_question("Hello.", 1), ['_', '.']);
		$t->equal(generate_question("Hello?", 1), ['_', '?']);
		$t->equal(generate_question("Hello!", 1), ['_', '!']);

		// doesn't choke on multiple words
		$t->equal(generate_question("Hello world!", 0.5), ['_', '_', '!']);
		$t->equal(generate_question("Hello, world!", 0.5), ['_', ',', '_', '!']);

        // multiple punctuation at end
		$t->equal(generate_question("Hello, world!?!", 0.5), ['_', ',', '_', '!?!']);

		// ignores conjunctions
		$t->equal(generate_question("Fred and Harry.", 0.5), ['_', 'and', '_', '.']);

		// Condenses consecutive trivial tokens
		$t->equal(generate_question("This is a pen.", 0.75), ['_', 'is a', '_', '.']);

		$t->print_results();
	}

	test_generate_question();*/

    //generate_question("Hello, mom!", 0.5);