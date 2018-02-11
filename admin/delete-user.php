<?php
    require_once(dirname(__FILE__)."/init-admin.php");
    
    $title = 'Delete User';

    $userID = $_GET["id"];

    $query = 'SELECT Username FROM Users WHERE UserID = ?';
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

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href="./view-users.php">Back</a></p>

<div id="delete-user">
    <h4> Are you sure you want to delete <?= $user["Username"] ?>? </h4>
    <form method="post">
        <input type="hidden" name="user-id" value="<?= $userID ?>"/>
        <button class="btn waves-effect waves-light submit red white-text" type="submit" name="action">Delete User</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>