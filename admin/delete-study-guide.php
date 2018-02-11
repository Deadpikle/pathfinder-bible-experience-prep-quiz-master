<?php
    require_once(dirname(__FILE__)."/init-admin.php");
    
    $title = 'Delete Study Guide';

    $id = $_GET["id"];
    $query = 'SELECT DisplayName, FileName FROM StudyGuides WHERE StudyGuideID = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$id]);
    $studyGuide = $stmt->fetch();
    if ($studyGuide == NULL) {
        die("Invalid study guide id");
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id == $_POST["study-guide-id"]) {
        if (!unlink("../" . $studyGuide["FileName"])) {
            die("Unable to delete study guide");
        }
        $query = 'DELETE FROM StudyGuides WHERE StudyGuideID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id]);
        header("Location: view-study-guides.php");
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href="./view-study-guides.php">Back</a></p>

<div id="delete-study-guide">
    <h4> Are you sure you want to delete the <?= $studyGuide["DisplayName"] ?> study guide?</h4>
    <form method="post">
        <input type="hidden" name="study-guide-id" value="<?= $id ?>"/>
        <button class="btn waves-effect waves-light submit red white-text" type="submit" name="action">Delete Study Guide</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>