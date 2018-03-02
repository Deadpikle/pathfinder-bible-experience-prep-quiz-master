<?php
    require_once(dirname(__FILE__)."/init-admin.php");
    
    $title = 'Delete Word';

    $wordID = $_GET["id"];
    $query = 'SELECT Word FROM BlankableWords WHERE WordID = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$wordID]);
    $word = $stmt->fetch();
    if ($word == NULL) {
        die("invalid word id");
    }
    
    if ($isPostRequest && $wordID == $_POST["word-id"]) {
        $query = 'DELETE FROM BlankableWords WHERE WordID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$wordID]);
        header("Location: view-non-blankable-words.php");
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href="./view-non-blankable-words.php">Back</a></p>

<div id="delete-word">
    <h4> Are you sure you want to delete '<?= $word["Word"] ?>' from the non-blankable words list?</h4>
    <form method="post">
        <input type="hidden" name="word-id" value="<?= $wordID ?>"/>
        <button class="btn waves-effect waves-light submit red white-text" type="submit" name="action">Delete Word</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>