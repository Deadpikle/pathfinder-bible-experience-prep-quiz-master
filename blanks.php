<?php
	require_once('./test.php');

	const SKIPPABLE = ['a', 'is', 'and', 'or', 'but', '.', '?', '!'];

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
	function generate_question($phrase, $num_blanks) {
		$words = tokenize($phrase);

		$blank_indexes = get_blanks($words, $num_blanks);

		foreach ($blank_indexes as $key) {
			$words[$key] = '_';
		}

		return condense($words);
	}

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

	function tokenize($phrase) {
		$words = explode(' ', $phrase);
		$result = [];

		foreach ($words as $word) {
			preg_match('/(\w+)( |\.|\?|!)?/', $word, $matches);

			// The word itself
			$result[] = $matches[1];

			// and separately, the punctuation.
			if ($matches[2]) {
				$result[] = $matches[2];
			}
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

		// ignores conjunctions
		$t->equal(generate_question("Fred and Harry.", 2), ['_', 'and', '_', '.']);

		// Condenses consecutive trivial tokens
		$t->equal(generate_question("This is a pen.", 2), ['_', 'is a', '_', '.']);

		$t->print_results();
	}

	test_generate_question();
?>
