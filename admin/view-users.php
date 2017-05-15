<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    $stmt = $pdo->query('SELECT UserID, FirstName, LastName, EntryCode, IsAdmin FROM Users');

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href="." class="back mdl-button mdl-js-button mdl-js-ripple-effect mdl-button--raised mdl-button--accent">Back</a></p>

<div id="create">
    <a class="mdl-button mdl-js-button mdl-js-ripple-effect mdl-button--raised mdl-button--colored" href="create-edit-user.php?type=create">Add User</a>
</div>

<div id="users-div">
    <table class="mdl-data-table mdl-js-data-table mdl-data-table mdl-shadow--2dp">
        <thead>
            <tr>
                <th>UserID</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Entry Code</th>
                <th>Admin?</th>
                <th>Edit</th>
                <th>Delete</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $stmt->fetch()) { ?>
                    <tr>
                        <td><?= $row["UserID"] ?></td>
                        <td><?= $row["FirstName"] ?></td>
                        <td><?= $row["LastName"] ?></td>
                        <td><?= $row["EntryCode"] ?></td>
                        <td><?= $row["IsAdmin"] ?></td>
                        <td><a href="create-edit-user.php?type=update&id=<?=$row['UserID'] ?>">Edit User</a></td>
                        <td><?php if ($_SESSION["UserID"] != $row["UserID"]) { ?> 
                                <a class="" href="delete-user.php?id=<?=$row['UserID'] ?>">Delete User</a>
                            <?php } ?> 
                         </td>
                    </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>