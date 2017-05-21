<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    $sectionID = $_GET["id"];
    $query = 'SELECT Name FROM HomeInfoSections WHERE HomeInfoSectionID = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$sectionID]);
    $section = $stmt->fetch();
    if ($section == NULL) {
        die("invalid section id");
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sectionID == $_POST["section-id"]) {
        $query = 'DELETE FROM HomeInfoSections WHERE HomeInfoSectionID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$sectionID]);
        header("Location: view-home-sections.php");
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href="./view-sections.php">Back</a></p>

<div id="delete-sectoin">
    <h4> Are you sure you want to delete <?= $section["Name"] ?>? </h4>
    <form method="post">
        <input type="hidden" name="section-id" value="<?= $sectionID ?>"/>
        <button class="btn waves-effect waves-light submit red white-text" type="submit" name="action">Delete Section</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>