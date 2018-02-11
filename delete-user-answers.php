<?php
    require_once(dirname(__FILE__)."/init.php");
    
    $title = 'Delete Answers';
    
    $userID = $_SESSION["UserID"];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST["user-id"] == $userID) {
        $query = 'DELETE FROM UserAnswers WHERE UserID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userID]);
        header("Location: quiz-setup.php");
    }

?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<p><a href="./quiz-setup.php">Back</a></p>

<div id="delete-user">
    <h4> Are you sure you want to delete all of your previously saved answers?</h4>
    <p>Questions you have answered correctly in the past will once again show up in quizzes if you choose to delete all of your answers. </p>
    <form method="post">
        <input type="hidden" name="user-id" value="<?= $userID ?>"/>
        <button class="btn waves-effect waves-light submit red white-text" type="submit" name="action">Delete Answers</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/footer.php"); ?>