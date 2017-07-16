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
    <table class="striped">
        <thead>
            <tr>
                <th>Display Name</th>
                <th>Rename</th>
                <th>Delete</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($files as $file) { ?>
                <tr>
                    <td><a target="_blank" class="btn-flat blue-text waves-effect waves-blue no-uppercase" href="<?= $basePath . '/' .$file['FileName'] ?>"><?= $file['DisplayName'] ?></a></td> 
                    <td><a class="btn waves-effect" 
                            href="rename-study-guide.php?id=<?= $file['StudyGuideID'] ?>">Rename</a></td> 
                    <td><a class="btn red white-text waves-effect waves-light" 
                            href="delete-study-guide.php?id=<?= $file['StudyGuideID'] ?>">Delete</a></td> 
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php include(dirname(__FILE__)."/../footer.php") ?>