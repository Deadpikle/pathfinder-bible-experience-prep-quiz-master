<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    if (!$isAdmin) {
        header("Location: $basePath/index.php");
        die();
    }

    $whereClause = "";
    $extraSelect = "";
    $extraJoin = "";
    $params = [];
    if ($isClubAdmin) {
        $whereClause = " WHERE u.ClubID = ? AND Type = 'Pathfinder'";
        $params[] = $_SESSION["ClubID"];
    }
    else if ($isConferenceAdmin) {
        $whereClause = " WHERE c.ConferenceID = ? AND Type <> 'ConferenceAdmin' AND Type <> 'WebAdmin'";
        $params[] = $_SESSION["ConferenceID"];
    }
    else if ($isWebAdmin) {
        $extraSelect = ", conf.Name AS ConferenceName";
        $extraJoin = "LEFT JOIN Conferences conf ON c.ConferenceID = conf.ConferenceID";
    }
    $query = '
        SELECT UserID, Username, EntryCode, ut.Type, ut.DisplayName AS UserTypeDisplayName, c.Name AS ClubName ' . $extraSelect . '
        FROM Users u JOIN UserTypes ut ON u.UserTypeID = ut.UserTypeID
            LEFT JOIN Clubs c ON u.ClubID = c.ClubID 
            ' . $extraJoin . '
        ' . $whereClause . '
        ORDER BY Username';
    $userStmt = $pdo->prepare($query);
    $userStmt->execute($params);
    $users = $userStmt->fetchAll();

    $displayConferenceName = $isConferenceAdmin || $isWebAdmin;
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href=".">Back</a></p>

<h4>Website Users</h4>

<p>Please note that any 0 in entry codes are zeros, not the capital letter O</p>

<div id="users-div">
    <?php if ($isClubAdmin) { ?>
        <h5>Club: <?= $_SESSION["ClubName"] ?></h5>
    <?php } else if ($isConferenceAdmin) { ?>
        <h5>Conference: <?= $_SESSION["ConferenceName"] ?></h5>
    <?php } ?>
        
    <div id="create">
        <a class="waves-effect waves-light btn" href="create-edit-user.php?type=create">Add User</a>
    </div>
    <table class="striped responsive-table">
        <thead>
            <tr>
                <th>Username</th>
                <th>Entry Code</th>
                <?php if ($isWebAdmin || $isConferenceAdmin) { ?>
                    <th>User Type</th>
                    <th>Club</th>
                <?php } ?>
                <?php if ($isWebAdmin) { ?>
                    <th>Conference</th>
                <?php } ?>
                <th></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user) { ?>
                    <?php 
                        $canEdit = true;
                        $userType = $user['Type'];
                        if ($isClubAdmin && ($userType == 'ClubAdmin' || $userType == 'ConferenceAdmin' || $userType == 'WebAdmin')) {
                            $canEdit = false;
                        }
                        if ($isConferenceAdmin && ($userType == 'ConferenceAdmin' || $userType == 'WebAdmin')) {
                            $canEdit = false;
                        }
                        if ($userType == 'Guest' && !$isWebAdmin) {
                            $canEdit = false; // only web admins can mess with guests
                        }
                        $entryCode = $canEdit ? $user["EntryCode"] : '';
                    ?>
                    <tr>
                        <td><?= $user["Username"] ?></td>
                        <td><?= $entryCode ?></td>
                        <?php if ($isWebAdmin || $isConferenceAdmin) { ?>
                            <td><?= $user["UserTypeDisplayName"] ?></td>
                            <td><?= $user["ClubName"] ?></td>
                        <?php } ?>
                        <?php if ($isWebAdmin) { ?>
                            <td><?= $user["ConferenceName"] ?></td>
                        <?php } ?>
                        <td>
                            <!-- edit this to not let you edit people above you -->
                            <?php if ($_SESSION["UserID"] != $user["UserID"] && $canEdit) { ?> 
                                <a class="waves-effect waves-light btn" href="create-edit-user.php?type=update&id=<?=$user['UserID'] ?>">Edit User</a>
                            <?php } ?> 
                        </td>
                        <td>
                            <?php if ($_SESSION["UserID"] != $user["UserID"] && $canEdit) { ?> 
                                <a class="waves-effect waves-light btn red white-text" href="delete-user.php?id=<?=$user['UserID'] ?>">Delete User</a>
                            <?php } ?> 
                         </td>
                    </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>