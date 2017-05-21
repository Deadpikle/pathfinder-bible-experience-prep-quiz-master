<?php
    require_once(dirname(__FILE__)."/init-admin.php");

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
    if ($line == NULL) {
        die("invalid line id"); // TODO: better error
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $query = 'DELETE FROM HomeInfoLines WHERE HomeInfoLineID = ?';
        $lineStmt = $pdo->prepare($query);
        $lineStmt->execute([$lineID]);
        header("Location: view-home-section-items.php?sectionID=" . $sectionID);
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href="./view-home-section-items.php?sectionID=<?=$sectionID?>">Back</a></p>

<div id="delete-line">
    <h4> Are you sure you want to delete this line of text?</h4>
    <form method="post">
        <button class="btn waves-effect waves-light submit red white-text" type="submit" name="action">Delete Line</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>