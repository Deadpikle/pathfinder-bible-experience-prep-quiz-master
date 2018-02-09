<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    $yearID = $_GET["id"];

    $query = 'SELECT Year FROM Years WHERE YearID = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$yearID]);
    $year = $stmt->fetch();
    if ($year == NULL) {
        die("invalid user id"); // TODO: better error
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $yearID == $_POST["year-id"]) {
        // clear current year
        $query = 'UPDATE Years SET IsCurrent = 0;';
        $stmt = $pdo->prepare($query);
        $stmt->execute([]);
        // set new current year
        $query = 'UPDATE Years SET IsCurrent = 1 WHERE YearID = ?;';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$yearID]);
        header("Location: view-years.php");
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href="./view-years.php">Back</a></p>

<div id="delete-user">
    <h4> Are you sure you want to make <?= $year["Year"] ?> the website's current year? This will change which questions are displayed, which books are used, which commentaries are used, and which users are available. Only do this if you're SURE you know what you're doing!</h4>
    <form method="post">
        <input type="hidden" name="year-id" value="<?= $yearID ?>"/>
        <button class="btn waves-effect waves-light submit blue white-text" type="submit" name="action">Make <?= $year["Year"] ?> the current year</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>