<?php
    require_once(dirname(__FILE__).'/init.php');

    $currentYear = get_active_year($pdo)["YearID"];
    $query = '
        SELECT StudyGuideID, DisplayName, FileName
        FROM StudyGuides 
        WHERE YearID = ' . $currentYear . '
        ORDER BY DisplayName';
    $stmt = $pdo->prepare($query);
    $stmt->execute([]);
    $files = $stmt->fetchAll();
?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href="./index.php">Back</a></p>

<h2>Study Guides</h2>

<div id="view-study-guides">
    <?php if (count($files) === 0) { ?>
        <p>There are no study guides available at this time.</p>
    <?php } else { ?>
        <ul class="browser-default">
            <?php foreach ($files as $file) { ?>
                <li>
                    <td><a target="_blank" class="btn-flat blue-text waves-effect waves-blue no-uppercase" href="<?= $basePath . '/' .$file['FileName'] ?>"><?= $file['DisplayName'] ?></a></td> 
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
</div>

<?php include(dirname(__FILE__)."/footer.php") ?>