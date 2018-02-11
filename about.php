<?php
    require_once(dirname(__FILE__)."/init.php");
    
    $title = 'About';
?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<h3>About</h3>

<div id="about">
    <div class="row">
        <div class="col s12">
            <p class="flow-text">The new <?= $websiteName ?> website was originally developed for <a href="http://www.pathfindersonline.org/">Pathfinder</a> clubs in the <a href="https://www.uccsda.org/">Upper Columbia Conference</a> of <a href="https://www.adventist.org/en/">Seventh-day Adventists</a>. If you have questions, concerns, or need to have your Pathfinder club added to this system, please contact:</p>
            <?php if (!$isGuest) { ?>
                <p class="flow-text"><?= $contactName ?><br><?= $contactEmail ?></p>
            <?php } else { ?>
                <p class="flow-text">[name redacted]<br>[email redacted]</p>
            <?php } ?>
            <p class="flow-text">If you're submitting information about your Pathfinder club, please send the club name, a link to the club's website or Facebook page (if available), and the name and email address of one or more club leaders who are in charge of the club.</p>
        </div>
    </div>
</div>

<?php include(dirname(__FILE__)."/footer.php") ?>