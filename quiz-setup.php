<?php

    require_once(dirname(__FILE__)."/init.php");

?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<p><a href=".">Back</a></p>

<div id="start-quiz">
    <h4>Quiz Setup</h4>
    <form action="ajax/save-question-edits.php?type=<?= $postType ?>" method="post">
        <p>Maximum number of questions and maximum number of points per question</p>
        <div class="row">
            <div class="input-field col s6 m3">
                <input type="number" id="max-questions" name="max-questions" required value="30" max="500"/>
                <label for="max-questions">Maximum Questions</label>
            </div>
            <div class="input-field col s6 m3">
                <input type="number" id="max-points" name="max-points" required value="25" max="500"/>
                <label for="max-points">Maximum Points</label>
            </div>
        </div>
        <p id="question-types">Question types (only Q&amp;A available at this point in time)</p>
        <div class="row">
            <div class="input-field col s12">
                <input type="radio" class="with-gap text-blue" name="types" id="both" disabled/>
                <label for="both">Both Q&amp;A and fill in the blank</label>
            </div>
            <div class="input-field col s12">
                <input type="radio" class="with-gap" name="types" id="qa-only" checked disabled/>
                <label for="qa-only">Q&amp;A only</label>
            </div>
            <div class="input-field col s12">
                <input type="radio" class="with-gap" name="types" id="fill-in-only" disabled/>
                <label for="fill-in-only">Fill in the blank only</label>
            </div>
            <!-- TODO: % of words blanked -->
        </div>
        <p id="question-order">Question order</p>
        <div class="row">
            <div class="input-field col s12">
                <input type="radio" class="with-gap text-blue" name="order" id="sequential-sequential" checked/>
                <label for="sequential-sequential">Sequential (Chapter verse sequence)</label>
            </div>
            <div class="input-field col s12">
                <input type="radio" class="with-gap" name="order" id="random-sequential" />
                <label for="random-sequential">Random selection and sequential order</label>
            </div>
            <div class="input-field col s12">
                <input type="radio" class="with-gap" name="order" id="random-random"  />
                <label for="random-random">Random selection and random order</label>
            </div>
        </div>
        <p id="question-filtering">Question history</p>
        <div class="row">
            <div class="input-field col s12">
                <input type="checkbox" id="filled-in-box" />
                <label for="filled-in-box">Only see questions answered incorrectly in the past</label>
            </div>
        </div>
        <button id="start-quiz-btn" class="btn waves-effect waves-light submit" type="submit" name="action">Start Quiz</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/footer.php") ?>