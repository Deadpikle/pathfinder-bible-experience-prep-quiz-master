<?php
    require_once(dirname(__FILE__)."/init.php");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $query = 'DELETE FROM Questions WHERE QuestionID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET["id"]]);
        header("Location: view-questions.php");
    }
    else {
        $query = 'SELECT Question, Answer FROM Questions WHERE QuestionID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET["id"]]);
        $question = $stmt->fetch();
    }

?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<p><a href="./view-questions.php">Back</a></p>

<div id="delete-user">
    <p> Are you sure you want to delete the question '<?= $question["Question"] ?>' with answer <?= $question["Answer"] ?>? </p>
    <form method="post">
        <input type="hidden" name="question-id" value="<?= $_GET['id'] ?>"/>
        <p>
            <input type="submit" value="Delete Question"/>
        </p>
    </form>
</div>

<?php include(dirname(__FILE__)."/footer.php"); ?>