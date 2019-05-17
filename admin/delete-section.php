<?php
    require_once(dirname(__FILE__)."/init-admin.php");
    
    $title = 'Delete Section';

    $sectionID = $_GET["id"];
    $conferenceID = $_GET["conferenceID"];
    $query = 'SELECT Name FROM HomeInfoSections WHERE HomeInfoSectionID = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$sectionID]);
    $section = $stmt->fetch();
    if ($section == null) {
        die("invalid section id");
    }
    
    if ($isPostRequest && $sectionID == $_POST["section-id"]) {
        $query = 'DELETE FROM HomeInfoSections WHERE HomeInfoSectionID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$sectionID]);
        header("Location: view-home-sections.php?conferenceID=" . $conferenceID);
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href="view-home-sections.php?conferenceID=<?= $conferenceID ?>">Back</a></p>

<div id="delete-section">
    <h4> Are you sure you want to delete <?= $section["Name"] ?>? </h4>
    <form method="post">
        <input type="hidden" name="section-id" value="<?= $sectionID ?>"/>
        <input type="hidden" name="conference-id" value="<?= $conferenceID ?>">
        <button class="btn waves-effect waves-light submit red white-text" type="submit" name="action">Delete Section</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>