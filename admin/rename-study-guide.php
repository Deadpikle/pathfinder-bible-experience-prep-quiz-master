<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    $id = $_GET["id"];
    $query = 'SELECT DisplayName, FileName FROM StudyGuides WHERE StudyGuideID = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$id]);
    $studyGuide = $stmt->fetch();
    if ($studyGuide == NULL) {
        die("Invalid study guide id");
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id == $_POST["study-guide-id"]) {
        $query = 'UPDATE StudyGuides SET DisplayName = ? WHERE StudyGuideID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_POST["display-name"], $id]);
        header("Location: manage-study-guides.php");
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href="./manage-study-guides.php">Back</a></p>

<h4>Rename Study Guide</h4>

<div id="delete-study-guide">
    <form method="post">
        <input type="hidden" name="study-guide-id" value="<?= $id ?>"/>
        <div class="row">
            <div class="input-field col s12 m4">
                <input type="text" id="display-name" name="display-name" data-length="300" required value="<?= $studyGuide['DisplayName'] ?>"/>
                <label for="display-name">Display Name</label>
            </div>
        </div>
        <button class="btn waves-effect waves-light submit" type="submit" name="action">Save</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>