<?php


use App\Models\NonBlankableWord;

require_once(dirname(__FILE__)."/../init.php");
    $sections = load_home_sections($pdo, $_SESSION["ConferenceID"]);

    $words = NonBlankableWord::loadAllBlankableWords($pdo);

    $question = NonBlankableWord::generateFillInQuestion("There was a boy called Eustace Clarence Scrubb, and he almost deserved it.", 1, $words);
    //$question = NonBlankableWord::generateFillInQuestion("There...almost deserved it.", 0.75, words);
    //$question = NonBlankableWord::generateFillInQuestion("\"My dear boy, what ever shall you do?\"", 0.5, words);
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>


<script type="text/javascript">
    var questionWords = <?= json_encode($question) ?>;
</script>

<div id="user-links">
    <p>Hello, <span contenteditable="true" class="browser-default fill-in-blank-input"></span>! <span class="only-bottom-border fill-in-blank-input" contenteditable="true">testificate</span> </p>
</div>
<p></p>
<div id="user-links-2">
    <p>Hello, <span><input class="browser-default fill-in-blank-input" type="text" value=""  data-autosize-input='{ "space": 4 }' /></span>!</p>
</div>
<div id="real-question">
    <p id="magic"></p>
</div>

<script type="text/javascript">
/*

                "before" => trim($matches[1]),
                "word" => $actualWord,
                "after" => trim($matches[3]),
                "blankable" => $isBlankable,
                "shouldBeBlanked" => false
*/


    $(document).ready(function() {
        // denying line breaks (enter key): https://stackoverflow.com/a/428139/3938401
        $("span").keypress(function(e) { return e.which != 13; } );

        $place = $("#magic");
        createFillInInput("#magic", questionWords.data);


    });

</script>

<?php include(dirname(__FILE__)."/../footer.php") ?>