<?php
    require_once("init-admin.php");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $query = 'DELETE FROM Users WHERE UserID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET["id"]]);
        header("Location: view-users.php");
    }
    else {
        $query = 'SELECT FirstName, LastName FROM Users WHERE UserID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET["id"]]);
        $user = $stmt->fetch();
    }

?>

<?php include("../header.php"); ?>

<p><a href="./view-users.php">Back</a></p>

<div id="delete-user">
    <p> Are you sure you want to delete <?= $user["FirstName"] ?> <?= $user["LastName"] ?> ? </p>
    <form method="post">
        <input type="hidden" name="user-id" value="<?= $_GET['id'] ?>"/>
        <p>
            <input type="submit" value="Delete User"/>
        </p>
    </form>
</div>

<?php include("../footer.php") ?>