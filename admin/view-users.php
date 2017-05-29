<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    $whereClause = "";
    $params = [];
    if ($isClubAdmin) {
        $whereClause = " WHERE u.ClubID = ?";
        $params[] = $_SESSION["ClubID"];
    }
    $query = '
        SELECT UserID, Username, EntryCode, ut.DisplayName AS UserTypeDisplayName, c.Name AS ClubName
        FROM Users u JOIN UserTypes ut ON u.UserTypeID = ut.UserTypeID
            LEFT JOIN Clubs c ON u.ClubID = c.ClubID 
        ' . $whereClause . '
        ORDER BY Username';
    $userStmt = $pdo->prepare($query);
    $userStmt->execute($params);
    $users = $userStmt->fetchAll();
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href=".">Back</a></p>

<p>Please note that any 0 in entry codes are zeros, not the capital letter O</p>

<div id="users-div">
    <?php if ($isClubAdmin) { ?>
        <h3><?= $_SESSION["ClubName"] ?></h3>
    <?php } ?>
    <div id="create">
        <a class="waves-effect waves-light btn" href="create-edit-user.php?type=create">Add User</a>
    </div>
    <table class="striped">
        <thead>
            <tr>
                <th>Username</th>
                <th>Entry Code</th>
                <?php if ($isWebAdmin) { ?>
                    <th>Club</th>
                    <th>User Type</th> <!-- TODO: only show for web admins -->
                <?php } ?>
                <th>Edit</th>
                <th>Delete</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user) { ?>
                    <tr>
                        <td><?= $user["Username"] ?></td>
                        <td><?= $user["EntryCode"] ?></td>
                        <?php if ($isWebAdmin) { ?>
                            <td><?= $user["ClubName"] ?></td>
                            <td><?= $user["UserTypeDisplayName"] ?></td>
                        <?php } ?>
                        <td><a href="create-edit-user.php?type=update&id=<?=$user['UserID'] ?>">Edit User</a></td>
                        <td><?php if ($_SESSION["UserID"] != $user["UserID"]) { ?> 
                                <a href="delete-user.php?id=<?=$user['UserID'] ?>">Delete User</a>
                            <?php } ?> 
                         </td>
                    </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>