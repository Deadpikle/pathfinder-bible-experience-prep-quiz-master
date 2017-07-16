<?php
    require_once(dirname(__FILE__).'/init-admin.php');

    $query = 'SELECT StudyGuideID, DisplayName, FileName FROM StudyGuides ORDER BY DisplayName';
    $stmt = $pdo->prepare($query);
    $stmt->execute([]);
    $files = $stmt->fetchAll();
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href="./index.php">Back</a></p>

<h2>Manage Study Guides</h2>

<div id="manage-study-guides">
    <a class="btn waves-effect waves-light" href="upload-study-guide.php">Upload Study Guide</a>
    <ul>
        <?php foreach ($files as $file) { ?>
            <li><?= $file['DisplayName'] ?> | Rename | Delete</li> <!-- TODO: rename, delete study guide -->
        <?php } ?>
    </ul>
</div>

<?php include(dirname(__FILE__)."/../footer.php") ?>