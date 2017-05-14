<?php
    require_once(dirname(__FILE__)."/init.php");

    if ($_GET["type"] == "update") {
        $query = 'SELECT Question, Answer, NumberPoints, IsFlagged FROM Questions WHERE QuestionID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET["id"]]);
        $question = $stmt->fetch();
        $questionText = $question["Question"];
        $answer = $question["Answer"];
        $numberOfPoints = $question["NumberPoints"];
        $isFlagged = $question["IsFlagged"];
        $postType = "update";
    }
    else {
        $questionText = "";
        $answer = "";
        $numberOfPoints = "";
        $isFlagged = FALSE;
        $postType = "create";
    }

?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<p><a href="./index.php">Back</a></p>

<div id="edit-question">
    <form action="ajax/save-question-edits.php?type=<?= $postType ?>" method="post">
        <input type="hidden" name="question-id" value="<?= $GET['id'] ?>"/>
        <p>
            <label for="question-text">Question: </label>
            <textarea name="question-text" value="<?= $questionText ?>"> </textarea>
        </p>
        <p>
            <label for="question-answer">Answer: </label>
            <textarea type="text" name="question-answer" value="<?= $answer ?>"></textarea>
        </p>
        <p>
            <label for="number-of-points">Number of Points: </label>
            <input type="number" min="0" name="number-of-points" value="<?= $numberOfPoints ?>"/>
        </p>
        <p>
            <input type="submit" value="Save"/>
        </p>
    </form>
</div>

<?php include(dirname(__FILE__)."/footer.php"); ?>