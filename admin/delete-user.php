<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    $query = 'SELECT FirstName, LastName FROM Users WHERE UserID = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_GET["id"]]);
    $user = $stmt->fetch();
    if ($user == NULL) {
        die("invalid user id"); // TODO: better error
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $query = 'DELETE FROM Users WHERE UserID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET["id"]]);
        header("Location: view-users.php");
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href="./view-users.php">Back</a></p>

<div id="delete-user">
    <h4> Are you sure you want to delete <?= $user["FirstName"] ?> <?= $user["LastName"] ?>? </h4>
    <form method="post">
        <input type="hidden" name="user-id" value="<?= $_GET['id'] ?>"/>
        <button class="btn waves-effect waves-light submit red white-text" type="submit" name="action">Delete User</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>