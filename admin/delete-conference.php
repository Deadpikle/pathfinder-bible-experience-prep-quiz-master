<?php
    require_once(dirname(__FILE__)."/init-admin.php");
    
    $title = 'Delete Conference';

    if (!$isWebAdmin) {
        header("Location: $basePath/index.php");
        die();
    }

    $conferenceID = $_GET["id"];
    $query = '
        SELECT Name 
        FROM Conferences
        WHERE ConferenceID = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$conferenceID]);
    $conference = $stmt->fetch();
    if ($conference == null) {
        die("Invalid commentary id");
    }
    
    if ($isPostRequest && $commentaryID == $_POST["commentary-id"]) {
        $query = 'DELETE FROM Conferences WHERE ConferenceID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$conferenceID]);
        header("Location: view-conferences.php");
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href="./view-conferences.php">Back</a></p>

<div id="delete-conference">
    <h4> Are you sure you want to delete the conference named <?= $conference["Name"] ?>?</h4>
    <form method="post">
        <input type="hidden" name="conference-id" value="<?= $commentaryID ?>"/>
        <button class="btn waves-effect waves-light submit red white-text" type="submit" name="action">Delete Conference</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>