<?php
    require_once(dirname(__FILE__)."/init.php");

    // load possible books and commentary volumes

    $chapterQuery = '
    SELECT DISTINCT b.BookID, b.Name, b.NumberChapters,
        c.ChapterID, c.Number AS ChapterNumber, c.NumberVerses
    FROM Books b 
        JOIN Chapters c ON b.BookID = c.BookID
        JOIN Verses v ON c.ChapterID = v.ChapterID
        JOIN Questions q ON v.VerseID = q.StartVerseID
    ORDER BY b.Name, ChapterNumber';
    $chapterData = $pdo->query($chapterQuery)->fetchAll();
    $chapters = array();
    foreach ($chapterData as $chapter) {
        $chapters[] =  array('id' => $chapter["ChapterID"], 'name' => $chapter["Name"], 'chapter' => $chapter["ChapterNumber"]);
    }

    $volumes = load_volumes($pdo);
    $lastBookSeen = "";

    $areAnyQuestionsAvailable = count($chapters) > 0 || count($volumes) > 0;
?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<p><a href=".">Back</a></p>

<h4>Please note that there is currently a bug preventing quizzes from generating properly in the Microsoft Edge and Internet Explorer web browsers. Please use Chrome, Firefox, or Safari. We apologize for the inconvenience.</h4>

<!--
https://stackoverflow.com/questions/18223743/to-generate-pdf-download-using-tcpdf
https://stackoverflow.com/questions/14765170/one-form-with-two-submit-buttons-and-different-actions-for-each-button

    -->

<?php if ($areAnyQuestionsAvailable) { ?>
<div id="start-quiz">
    <h4>Quiz Setup</h4>
    <form method="post">
        <p>Choose Bible Chapters &amp; Commentary Volumes to Be Quizzed On -- for Bible Q&amp;A questions, questions are loaded by chapter based on the question's start verse</p>
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
                <label>Bible Chapters &amp; Commentary Volumes with Created Questions</label>
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
        <p id="question-types">Question types</p>
        <div class="row">
            <div class="input-field col s12">
                <input type="radio" class="with-gap" name="question-types" id="both" value="both"/>
                <label class="black-text" for="both">Both Q&amp;A and fill in the blank</label>
            </div>
            <div class="input-field col s12">
                <input type="radio" class="with-gap" name="question-types" id="qa-only" value="qa-only" checked/>
                <label class="black-text" for="qa-only">Q&amp;A only</label>
            </div>
            <div class="input-field col s12">
                <input type="radio" class="with-gap" name="question-types" id="fill-in-only" value="fill-in-only"/>
                <label class="black-text" for="fill-in-only">Fill in the blank only</label>
            </div>
            <div class="input-field col s2" id="fill-in-percent-div">
                <input type="number" name="fill-in-percent" id="fill-in-percent" value="30" min="1" max="100"/>
                <label class="" for="fill-in-percent">% Blanks</label>
            </div>
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
        <p id="question-filtering">Flash card options (Only apply to flash cards)</p>
        <div class="row">
            <div class="input-field col s12">
                <input type="checkbox" id="flash-show-recently-added" name="flash-show-recently-added"/>
                <label class="black-text" for="flash-show-recently-added">Show recently added questions (overrides ALL above settings!)</label>
            </div>
            <div class="input-field col s12 m4" id="fill-in-percent-div">
                <input type="number" name="flash-recently-added-days" id="flash-recently-added-days" value="30" min="1" max="31"/>
                <label class="" for="flash-recently-added-days">Number of days to go back in time for recently added questions</label>
            </div>
        </div>
        <button id="start-quiz-btn" class="btn waves-effect waves-light submit" type="submit" name="action" formaction="quiz.php">Start Quiz</button>
        <button id="flash-cards-btn" class="btn waves-effect waves-light submit" type="submit" name="action" formaction="study-guide-pdf.php" >Flash Cards</button>
        <div class="divider"></div>
        <div class="input-field col s6">
            <a id="save-data" class="btn btn-flat red white-text waves-effect red-waves right-margin" href="delete-user-answers.php">Erase previously saved answers</a>
        </div>
    </form>
</div>
<?php } else { ?>
<div id="start-quiz">
    <h4>Quiz Setup</h4>
    <p>Sorry! No quiz questions have been created yet! Why don't you go <a href="add-edit-question.php?type=create">create one</a>?</p>
</div>
<?php } ?>

<?php include(dirname(__FILE__)."/footer.php") ?>


<script type="text/javascript">
    // http://stackoverflow.com/a/15965470/3938401
    $(document).ready(function() {
        var bibleQuestionType = document.getElementById('quiz-items');
        $(bibleQuestionType).material_select();
        fixRequiredSelectorCSS();

        $('#start-quiz-btn').click(function() {
            $('form').attr('target', '').submit();
        });
        $('#flash-quiz-btn').click(function() {
            $('form').attr('target', '_blank').submit();
            return false;
        });

        var buttonID = '';
        $(':submit').click(function() {
            buttonID = $(this).attr('id');
        })
        $('form').submit(function() {
            if (buttonID != '') {
                if (buttonID === 'flash-cards-btn') {
                    this.target = '_blank';
                }
                else {
                    this.target = '';
                }
            }
            buttonID = '';
            return true;
        })

    });
</script>