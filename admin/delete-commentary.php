<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    if (!$isWebAdmin) {
        header("Location: $basePath/index.php");
        die();
    }

    $commentaryID = $_GET["id"];
    $query = '
        SELECT Number, Year 
        FROM Commentaries c JOIN Years y ON c.YearID = y.YearID
        WHERE CommentaryID = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$commentaryID]);
    $commentary = $stmt->fetch();
    if ($commentary == NULL) {
        die("Invalid commentary id");
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $commentaryID == $_POST["commentary-id"]) {
        $query = 'DELETE FROM Commentaries WHERE CommentaryID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$commentaryID]);
        header("Location: view-commentaries.php");
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href="./view-commentaries.php">Back</a></p>

<div id="delete-commentary">
    <h4> Are you sure you want to remove SDA Bible Commentary <?= $commentary["Number"] ?> for the year <?= $commentary["Year"] ?> from the available commentaries list?</h4>
    <form method="post">
        <input type="hidden" name="commentary-id" value="<?= $commentaryID ?>"/>
        <button class="btn waves-effect waves-light submit red white-text" type="submit" name="action">Remove Commentary</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>