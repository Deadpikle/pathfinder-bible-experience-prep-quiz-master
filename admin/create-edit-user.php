<?php
    require_once("init-admin.php");

    if ($_GET["type"] == "update") {
        $stmt = $pdo->query('SELECT UserID, FirstName, LastName, EntryCode, IsAdmin FROM Users');
        $user = $stmt->fetch();
    }
    else {

    }


?>

<?php include("../header.php"); ?>

<p><a href=".">Back</a></p>

<div id="edit-user">
    <form action="save-user-edits.php?type=update" method="post">
        <input type="hidden" name="user-id" value="<?php $user ?? $user['UserID'] ?>"/>
        <p>
            <label for="first-name">First Name: </label>
            <input type="text" name="first-name" value="<?= $user['FirstName'] ?>"/>
        </p>
        <p>
            <label for="last-name">Last Name: </label>
            <input type="text" name="last-name" value="<?= $user['LastName'] ?>"/>
        </p>
        <p>
            <label for="entry-code">Entry Code: </label>
            <input type="text" name="entry-code" value="<?= $user['EntryCode'] ?>"/>
        </p>
        <p>
            <label for="is-admin">Administrator? </label>
            <input type="checkbox" name="is-admin" checked="<?= $user['IsAdmin'] ?>"/>
        </p>
        <p>
            <input type="submit" value="Save"/>
        </p>
    </form>
</div>

<?php include("../footer.php") ?>