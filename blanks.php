<?php
  require_once('./test.php');

  const CONJUNCTIONS = ['and', 'or', 'but'];

  function generate_question($phrase, $num_blanks) {
    $words = explode(' ', $phrase);
    $result = [];

    foreach ($words as $word) {
      preg_match('/(\w+)( |\.|\?|!)?/', $word, $matches);

      if (in_array($matches[1], CONJUNCTIONS)) {
        $result[] = $matches[1];
      } else {
        $result[] = '_';
      }

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
