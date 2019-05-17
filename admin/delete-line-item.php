<?php
    require_once(dirname(__FILE__)."/init-admin.php");
    
    $title = 'Delete Info Item';

    $conferenceID = $_GET["conferenceID"];
    $sectionID = $_GET["sectionID"];
    $itemID = $_GET["itemID"];

    // validate line ID
    $query = '
        SELECT hil.HomeInfoItemID AS ItemID
        FROM HomeInfoItems hil
        WHERE hil.HomeInfoItemID = ?';
    $itemStmt = $pdo->prepare($query);
    $itemStmt->execute([$itemID]);
    $item = $itemStmt->fetch();
    if ($item == null) {
        die("invalid line id"); // TODO: better error
    }

    if ($isPostRequest && $itemID == $_POST["item-id"]) {
        $query = 'DELETE FROM HomeInfoItems WHERE HomeInfoItemID = ?';
        $itemStmt = $pdo->prepare($query);
        $itemStmt->execute([$itemID]);
        header("Location: view-home-section-items.php?sectionID=" . $sectionID . "&conferenceID=" . $conferenceID);
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p>
    <a class="btn-flat blue-text waves-effect waves-blue no-uppercase" 
       href="./view-home-section-items.php?sectionID=<?= $sectionID ?>&conferenceID=<?= $conferenceID ?>">Back</a>
</p>

<div id="delete-line">
    <h4> Are you sure you want to delete this line item?</h4>
    <form method="post">
        <input type="hidden" name="item-id" value="<?= $itemID ?>"/>
        <button class="btn waves-effect waves-light submit red white-text" type="submit" name="action">Delete Line Item</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>