<?php
// blanks a minimum of 1 word
	//require_once('./test.php');

	const SKIPPABLE = ['a', 'is', 'and', 'or', 'but', 'the', '...'];
    const PUNCTUATION = ['.', '?', '!', ',', ' '];
    const DEBUG = false;

    // $percentToBlank should be a decimal
	function generate_fill_in_question($phrase, $percentToBlank, $nonBlankableWords) {
        if (DEBUG) {
            echo '<br>-----<br>';
            echo $phrase;
            echo '<br>';
        }
		$tokenized = tokenize($phrase, $nonBlankableWords);

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
        
        if (DEBUG) {
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

    function tokenize($phrase, $nonBlankableWords) {
        preg_match_all("/[^\s]+/", $phrase, $words);
        $words = $words[0];
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
            preg_match("/^([^\w]*)(.*?)([^\w]*)$/", $word, $matches);
            $actualWord = trim($matches[2]);
            $isBlankable = array_search(strtolower($actualWord), $nonBlankableWords) === false ? true : false;
            if ($isBlankable && !is_numeric($actualWord)) {
                if (DEBUG) {
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
?>
