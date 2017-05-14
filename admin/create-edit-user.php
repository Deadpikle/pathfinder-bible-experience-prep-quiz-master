<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    if ($_GET["type"] == "update") {
        $query = 'SELECT UserID, FirstName, LastName, EntryCode, IsAdmin FROM Users WHERE UserID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET["id"]]);
        $user = $stmt->fetch();
        $userID = $user["UserID"];
        $firstName = $user["FirstName"];
        $lastName = $user["LastName"];
        $entryCode = $user["EntryCode"];
        $isAdmin = $user["IsAdmin"];
        $postType = "update";
    }
    else {
        $userID = "";
        $firstName = "";
        $lastName = "";
        $entryCode = "";
        $isAdmin = FALSE;
        $postType = "create";
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href="./view-users.php">Back</a></p>

<div id="edit-user">
    <form action="ajax/save-user-edits.php?type=<?= $postType ?>" method="post">
        <input type="hidden" name="user-id" value="<?= $userID ?>"/>
        <p>
            <label for="first-name">First Name: </label>
            <input type="text" name="first-name" value="<?= $firstName ?>"/>
        </p>
        <p>
            <label for="last-name">Last Name: </label>
            <input type="text" name="last-name" value="<?= $lastName ?>"/>
        </p>
        <p>
            <label for="entry-code">Entry Code: </label>
            <input type="text" name="entry-code" value="<?= $entryCode ?>"/>
        </p>
        <p>
            <label for="is-admin">Administrator? </label>
            <input type="checkbox" name="is-admin" <?php if ($isAdmin) { ?> checked <?php } ?>/>
        </p>
        <p>
            <input type="submit" value="Save"/>
        </p>
    </form>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>