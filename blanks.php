<?php
  require_once('./test.php');

  const SKIPPABLE = ['and', 'or', 'but', '.', '?', '!'];

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

    foreach ($words as $key => $word) {
      if (!in_array($word, SKIPPABLE)) {
        $words[$key] = '_';
      }
    }

    return $words;
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

    $t->print_results();
  }

  test_generate_question();
?>
