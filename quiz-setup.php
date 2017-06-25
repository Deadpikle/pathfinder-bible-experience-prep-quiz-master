<?php
    require_once(dirname(__FILE__)."/init.php");

    // load possible books and commentary volumes

    $chapterQuery = '
    SELECT b.BookID, b.Name, b.NumberChapters,
        c.ChapterID, c.Number AS ChapterNumber, c.NumberVerses
    FROM Books b 
        JOIN Chapters c ON b.BookID = c.BookID
    ORDER BY b.Name, ChapterNumber';
    $chapterData = $pdo->query($chapterQuery)->fetchAll();
    $chapters = array();
    foreach ($chapterData as $chapter) {
        $chapters[] =  array('id' => $chapter["ChapterID"], 'name' => $chapter["Name"], 'chapter' => $chapter["ChapterNumber"]);
    }

    $volumeQuery = '
    SELECT DISTINCT CommentaryVolume
    FROM Questions q
    WHERE CommentaryVolume IS NOT NULL AND CommentaryVolume <> 0
    ORDER BY CommentaryVolume';
    $volumeData = $pdo->query($volumeQuery)->fetchAll();
    $volumes = array();
    foreach ($volumeData as $volume) {
        $volumes[] = array('id' => $volume["CommentaryVolume"], 'name' => "SDA Commentary Volume " . $volume["CommentaryVolume"]);
    }
    $lastBookSeen = "";
?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<p><a href=".">Back</a></p>

<div id="start-quiz">
    <h4>Quiz Setup</h4>
    <form action="quiz.php" method="post">
        <p>Chapter &amp; Commentary Choices -- for Bible Q&amp;A questions, questions are loaded by chapter based on the question's start verse</p>
        <div class="row">
            <div class="input-field col s12 m6 l6">
                <select multiple id="quiz-items" name="quiz-items[]">
                    <option value="" disabled selected>All</option>
                    <?php 
                        foreach ($chapters as $chapter) { 
                            if ($lastBookSeen != $chapter['name']) {
                                if ($lastBookSeen != $chapter['name']) {
                                    echo '</optgroup>';
                                }
                                echo '<optgroup label="' . $chapter['name'] . '">';
                                $lastBookSeen = $chapter['name'];
                            }
                    ?>
                            <option value="chapter-<?= $chapter['id'] ?>"><?= $chapter['name'] ?>&nbsp;<?= $chapter['chapter'] ?></option>
                    <?php } 
                        echo '</optgroup>';
                    ?>
                    <optgroup label="SDA Bible Commentary">
                        <?php foreach ($volumes as $volume) { ?>
                            <option value="commentary-<?= $volume['id'] ?>"><?= $volume['name'] ?></option>
                        <?php } ?>
                    </optgroup>
                </select>
                <label>Chapters &amp; Commentary Volumes</label>
            </div>
        </div>
        <p class="negative-top-margin">Maximum number of questions and maximum number of points per question</p>
        <div class="row">
            <div class="input-field col s6 m3">
                <input type="number" id="max-questions" name="max-questions" required value="30" max="500" min="1"/>
                <label for="max-questions">Maximum Questions</label>
            </div>
            <div class="input-field col s6 m3">
                <input type="number" id="max-points" name="max-points" required value="25" max="500" min="0"/>
                <label for="max-points">Maximum Points</label>
            </div>
        </div>
        <p id="question-types">Question types (only Bible/Commentary Q&amp;A available at this point in time)</p>
        <div class="row">
            <div class="input-field col s12">
                <input type="radio" class="with-gap" name="question-types" id="both" value="both" disabled/>
                <label class="black-text" for="both">Both Q&amp;A and fill in the blank</label>
            </div>
            <div class="input-field col s12">
                <input type="radio" class="with-gap" name="question-types" id="qa-only" value="qa-only" checked/>
                <label class="black-text" for="qa-only">Q&amp;A only</label>
            </div>
            <div class="input-field col s12">
                <input type="radio" class="with-gap" name="question-types" id="fill-in-only" value="fill-in-only" disabled/>
                <label class="black-text" for="fill-in-only">Fill in the blank only</label>
            </div>
            <!-- TODO: % of words blanked -->
        </div>
        <p id="question-order">Question selection and order</p>
        <div class="row">
            <div class="input-field col s12">
                <input type="radio" class="with-gap text-blue" name="order" id="sequential-sequential" value="sequential-sequential" />
                <label class="black-text" for="sequential-sequential">Sequential</label>
            </div>
            <div class="input-field col s12">
                <input type="radio" class="with-gap" name="order" id="random-sequential" value="random-sequential" checked/>
                <label class="black-text" for="random-sequential">Random selection and sequential order</label>
            </div>
            <div class="input-field col s12">
                <input type="radio" class="with-gap" name="order" id="random-random" value="random-random"  />
                <label class="black-text" for="random-random">Random selection and random order</label>
            </div>
        </div>
        <p id="question-filtering">Question history</p>
        <div class="row">
            <div class="input-field col s12">
                <input type="checkbox" id="no-questions-answered-correct" name="no-questions-answered-correct"/>
                <label class="black-text" for="no-questions-answered-correct">Don't see questions answered correctly in the past</label>
            </div>
        </div>
        <button id="start-quiz-btn" class="btn waves-effect waves-light submit" type="submit" name="action">Start Quiz</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/footer.php") ?>


<script type="text/javascript">
    // http://stackoverflow.com/a/15965470/3938401
    $(document).ready(function() {
        var bibleQuestionType = document.getElementById('quiz-items');
        $(bibleQuestionType).material_select();
        fixRequiredSelectorCSS();
    });
</script>