<?php
    require_once(dirname(__FILE__)."/init.php");
    
    $title = 'Delete Question';
    
    if ($isGuest) {
        header('Location: index.php');
        die();
    }

    $questionID = $_GET["id"];

    $query = 'SELECT Question, Answer FROM Questions WHERE QuestionID = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$questionID]);
    $question = $stmt->fetch();
    if ($question == NULL) {
        die("invalid question id");
    }
    
    if ($isPostRequest && $questionID == $_POST["question-id"]) {
        $query = 'Update Questions SET IsDeleted = 1 WHERE QuestionID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$questionID]);
        header("Location: view-questions.php");
    }

?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href="./view-questions.php">Back</a></p>

<div id="delete-user">
    <p> Are you sure you want to delete the question '<?= $question["Question"] ?>' with answer '<?= $question["Answer"] ?>'? </p>
    <form method="post">
        <input type="hidden" name="question-id" value="<?= $questionID ?>"/>
        <button class="btn waves-effect waves-light submit red white-text" type="submit" name="action">Delete Question</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/footer.php"); ?>