<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $query = 'DELETE FROM HomeInfoSections WHERE HomeInfoSectionID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET["id"]]);
        header("Location: view-home-sections.php");
    }
    else {
        $query = 'SELECT Name FROM HomeInfoSections WHERE HomeInfoSectionID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET["id"]]);
        $section = $stmt->fetch();
        if ($section == NULL) {
            die("invalid section id"); // TODO: better error
        }
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href="./view-sections.php">Back</a></p>

<div id="delete-sectoin">
    <h4> Are you sure you want to delete <?= $section["Name"] ?>? </h4>
    <form method="post">
        <input type="hidden" name="section-id" value="<?= $_GET['id'] ?>"/>
        <button class="btn waves-effect waves-light submit" type="submit" name="action">Delete Section</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>