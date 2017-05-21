<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    $userID = $_GET["id"];

    $query = 'SELECT FirstName, LastName FROM Users WHERE UserID = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$userID]);
    $user = $stmt->fetch();
    if ($user == NULL) {
        die("invalid user id"); // TODO: better error
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userID == $_POST["user-id"]) {
        $query = 'DELETE FROM Users WHERE UserID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userID]);
        header("Location: view-users.php");
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href="./view-users.php">Back</a></p>

<div id="delete-user">
    <h4> Are you sure you want to delete <?= $user["FirstName"] ?> <?= $user["LastName"] ?>? </h4>
    <form method="post">
        <input type="hidden" name="user-id" value="<?= $userID ?>"/>
        <button class="btn waves-effect waves-light submit red white-text" type="submit" name="action">Delete User</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>