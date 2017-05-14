<?php
    require_once("init-admin.php");

    $stmt = $pdo->query('SELECT UserID, FirstName, LastName, EntryCode, IsAdmin FROM Users');

?>

<?php include("../header.php"); ?>

<p><a href=".">Back</a></p>

<div id="create">
    <a href="create-edit-user.php?type=create">Add User</a>
</div>

<div id="users-div">
    <table>
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
                                <a href="delete-user.php?id=<?=$row['UserID'] ?>">Delete User</a>
                            <?php } ?> 
                         </td>
                    </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php include("../footer.php") ?>