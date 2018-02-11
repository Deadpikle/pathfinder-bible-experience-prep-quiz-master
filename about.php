<?php
    require_once(dirname(__FILE__)."/init.php");
    
    $title = 'About';

    $conferences = [];
    $query = '
        SELECT Name, URL, ContactName, ContactEmail
        FROM Conferences
        WHERE Name NOT LIKE "%Website%"
        ORDER BY Name';
    $conferencesStmt = $pdo->prepare($query);
    $conferencesStmt->execute([]);
    $conferences = $conferencesStmt->fetchAll();
?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<h3>About</h3>

<div id="about">
    <div class="row">
        <div class="col s12">
            <p class="flow-text">The new <?= $websiteName ?> website was originally developed for <a href="http://www.pathfindersonline.org/">Pathfinder</a> clubs in the <a href="https://www.uccsda.org/">Upper Columbia Conference</a> of <a href="https://www.adventist.org/en/">Seventh-day Adventists</a>.</p>
            <p class="flow-text">If you have questions, concerns, or need to have your Pathfinder club added to this system, please contact the adminstrator for your conference:</p>
            <?php foreach ($conferences as $conference) { ?>
                <p class="flow-text"><a href="<?= $conference["URL"] ?>" target="_blank"><b><?= $conference["Name"] ?></b></a><br><?= $conference["ContactName"] ?><br><?= $conference["ContactEmail"] ?></p>
            <?php } ?>
            <p class="flow-text">For all other questions, or to have your conference added to this system, please contact:</p>
            <p class="flow-text"><?= $contactName ?><br><?= $contactEmail ?></p>
            <p class="flow-text">If you're submitting information about your Pathfinder club, please send the club name, a link to the club's website or Facebook page (if available), and the name and email address of one or more club leaders who are in charge of the club.</p>
            <p class="flow-text">If you're submitting information about your conference, please send the conference name, a link to the conference's website, and the name and email address of person who will administer website use for your conference.</p>
        </div>
    </div>
</div>

<?php include(dirname(__FILE__)."/footer.php") ?>