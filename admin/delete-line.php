<?php
    require_once(dirname(__FILE__)."/init-admin.php");
    
    $title = 'Delete Info Line';

    $conferenceID = $_GET["conferenceID"];
    $sectionID = $_GET["sectionID"];
    $lineID = $_GET["lineID"];

    // validate line ID
    $query = '
        SELECT hil.HomeInfoLineID AS LineID
        FROM HomeInfoLines hil
        WHERE hil.HomeInfoLineID = ?';
    $lineStmt = $pdo->prepare($query);
    $lineStmt->execute([$lineID]);
    $line = $lineStmt->fetch();
    if ($line == null) {
        die("invalid line id"); // TODO: better error
    }

    if ($isPostRequest && $lineID == $_POST["line-id"]) {
        $query = 'DELETE FROM HomeInfoLines WHERE HomeInfoLineID = ?';
        $lineStmt = $pdo->prepare($query);
        $lineStmt->execute([$lineID]);
        header("Location: view-home-section-items.php?sectionID=" . $sectionID . "&conferenceID=" . $conferenceID);
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p>
    <a class="btn-flat blue-text waves-effect waves-blue no-uppercase" 
       href="./view-home-section-items.php?sectionID=<?= $sectionID ?>&conferenceID=<?= $conferenceID ?>">Back</a>
</p>

<div id="delete-line">
    <h4> Are you sure you want to delete this line of text?</h4>
    <form method="post">
        <input type="hidden" name="line-id" value="<?= $lineID ?>"/>
        <button class="btn waves-effect waves-light submit red white-text" type="submit" name="action">Delete Line</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>