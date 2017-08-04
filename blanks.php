<?php
	require_once('./test.php');

	const SKIPPABLE = ['a', 'is', 'and', 'or', 'but', 'the'];
    const PUNCTUATION = ['.', '?', '!', ',', ' '];

	/*
	 * words = verse
	 * |> tokenize
	 *
	 * blanks = words
	 * |> get_indexes
	 * |> filter_trivial
	 * |> shuffle
	 * |> take(n)
	 *
	 * result = words
	 * |> replace(blanks)
	 * |> concat
	 */

    // $percentToBlank should be a decimal
	function generate_question($phrase, $percentToBlank) {
		$tokenized = tokenize($phrase);
        // echo $phrase;
        // echo '<br>';
        // print_r($words);
        // echo '<br>';

        $data = $tokenized["word-data"];
        $blankableIndices = $tokenized["blankable-indices"];
        $numberWords = count($data);
        $numberToBlank = floor($percentToBlank * count($blankableIndices));

        // get items we should blank
		shuffle($blankableIndices);
		$blankableIndices = array_slice($blankableIndices, 0, $numberToBlank);
        for ($i = 0; $i < count($blankableIndices); $i++) {
            $data[$blankableIndices[$i]]["shouldBeBlanked"] = TRUE;
        }
        echo '<br>-----<br>';
        echo $phrase;
        echo '<br>';
        print_r($data);
        echo '<br>';
        return $data;
	}

    function tokenize($phrase) {
        preg_match_all("/[^\s]+/", $phrase, $words);
        $words = $words[0];
        $word_arrays = [];
        $blankableIndices = [];
        for ($i = 0; $i < count($words); $i++) {
            $word = $words[$i];
            preg_match("/^([^\w]*)(.*?)([^\w]*)$/", $word, $matches);
            $actualWord = trim($matches[2]);
            $isBlankable = array_search($actualWord, SKIPPABLE) == FALSE ? TRUE : FALSE;
            if ($isBlankable) {
                $blankableIndices[] = $i;
            }
            $word_array = [
                "before" => trim($matches[1]),
                "word" => $actualWord,
                "after" => trim($matches[3]),
                "blankable" => $isBlankable,
                "shouldBeBlanked" => FALSE
            ];
            $word_arrays[] = $word_array;
        }
        return [
            "blankable-indices" => $blankableIndices,
            "word-data" => $word_arrays
        ];
    }

    // technically this works, but it is much less awesome than @stephenwade's regex solution
    /*
	function tokenize($phrase) {
		$words = explode(' ', $phrase);
		$result = [];

		foreach ($words as $word) {
            $strlen = strlen($word);
            $isSearchingBefore = TRUE;
            $isReadingWord = FALSE;
            $isSearchingAfter = FALSE;
            $beforePunctuation = "";
            $afterPunctuation = "";
            $actualWord = "";
            for ($i = 0; $i < strlen($word); $i++) {
                $char = $word[$i];
                // $char contains the current character, so do your processing here
                $key = array_search($char, PUNCTUATION);
                //echo $key . ' ';
                $isPunct = $key !== FALSE;
                if ($isSearchingBefore && $isPunct) {
                    $beforePunctuation .= $char;
                }
                else if ($isSearchingBefore && !$isPunct) {
                    $isSearchingBefore = FALSE;
                    $isReadingWord = TRUE;
                    $actualWord .= $char;
                }
                else if ($isReadingWord && !$isPunct) {
                    $actualWord .= $char;
                }
                else {
                    $isReadingWord = FALSE;
                    $isSearchingAfter = TRUE;
                    $afterPunctuation .= $char;
                }
                //echo '[key= ' . $key . ', ispunct = ' . $isPunct . '][' . $beforePunctuation . '][' . $actualWord . '][' . $afterPunctuation . ']<br>';
            }
            $innerResult = [
                "before" => "",
                "word" => "",
                "after" => "",
                "blankable" => FALSE
            ];
            $didAdd = FALSE;
            if ($beforePunctuation !== "") {
                $innerResult["before"] = $beforePunctuation;
                $didAdd = TRUE;
                //echo 'adding before - ';
            }
            if ($actualWord !== "") {
                $innerResult["word"] = trim($actualWord);
                $didAdd = TRUE;
                if (array_search($actualWord, SKIPPABLE) !== FALSE) {
                    $innerResult["blankable"] = true;
                }
                //echo 'adding actual - ';
            }
            if ($afterPunctuation !== "") {
                $innerResult["after"] = $afterPunctuation;
                $didAdd = TRUE;
                //echo 'adding after - ';
            }
            if ($didAdd) {
                $result[] = $innerResult;
            }
            //echo '<br>';
		}
		return $result;
	}*/

	function condense($tokens) {
		$result = [];
		$acc = '';

		foreach ($tokens as $token) {
			if ($token == '_') {
				if ($acc) {
					$result[] = $acc;
					$acc = '';
				}
				$result[] = $token;
			} else {
				$acc = $acc ? ($acc . ' ' . $token) : $token;
			}
		}

		if ($acc) {
			$result[] = $acc;
		}

		return $result;
	}

	function get_blanks($tokens, $num_blanks) {
		$keys = array_keys($tokens);

		$blankable = array_filter($keys, function ($key) use ($tokens) {
			return !in_array($tokens[$key], SKIPPABLE);
		});

		shuffle($blankable);

		return array_slice($blankable, 0, $num_blanks);
	}

	// #generate_question
	function test_generate_question() {
		$t = new Test();

		// replaces a word with a blank
		$t->equal(generate_question("Hello", 1), ['_']);

		// doesn't include punctuation in the blank
		$t->equal(generate_question("Hello.", 1), ['_', '.']);
		$t->equal(generate_question("Hello?", 1), ['_', '?']);
		$t->equal(generate_question("Hello!", 1), ['_', '!']);

		// doesn't choke on multiple words
		$t->equal(generate_question("Hello world!", 2), ['_', '_', '!']);
		$t->equal(generate_question("Hello, world!", 2), ['_', ',', '_', '!']);

        // multiple punctuation at end
		$t->equal(generate_question("Hello, world!?!", 2), ['_', ',', '_', '!?!']);

		// ignores conjunctions
		$t->equal(generate_question("Fred and Harry.", 2), ['_', 'and', '_', '.']);

		// Condenses consecutive trivial tokens
		$t->equal(generate_question("This is a pen.", 2), ['_', 'is a', '_', '.']);

		$t->print_results();
	}

	test_generate_question();
?>
