<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    $clubID = $_GET["id"];
    $query = 'SELECT Name FROM Clubs WHERE ClubID = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$clubID]);
    $club = $stmt->fetch();
    if ($club == NULL) {
        die("invalid club id");
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $clubID == $_POST["club-id"]) {
        $query = 'DELETE FROM Clubs WHERE ClubID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$clubID]);
        header("Location: view-clubs.php");
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href="./view-clubs.php">Back</a></p>

<div id="delete-club">
    <h4> Are you sure you want to delete <?= $club["Name"] ?>? Any users who belong to this club will need to be reassigned to another club by a website administrator.</h4>
    <form method="post">
        <input type="hidden" name="club-id" value="<?= $clubID ?>"/>
        <button class="btn waves-effect waves-light submit red white-text" type="submit" name="action">Delete Club</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>