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
        <div class="row">
            <div class="input-field col s12 m4">
                <input type="text" id="first-name" name="first-name" value="<?= $firstName ?>"/>
                <label for="first-name">First Name</label>
            </div>
            <div class="input-field col s12 m4">
                <input type="text" id="last-name" name="last-name" value="<?= $lastName ?>"/>
                <label for="last-name">Last Name</label>
            </div>
            <div class="input-field col s12 m4">
                <input type="text" id="entry-code" name="entry-code" value="<?= $entryCode ?>"/>
                <label for="entry-code">Entry Code</label>
            </div>
        </div>
        <div class="row">
            <input type="checkbox" name="is-admin" id="is-admin" <?php if ($isAdmin) { ?> checked <?php } ?>/>
            <label for="is-admin">Administrator? </label>
        </div>
        <button class="btn waves-effect waves-light" type="submit" name="action">Save</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>