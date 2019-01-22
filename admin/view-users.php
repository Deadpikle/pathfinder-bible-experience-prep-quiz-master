<?php
    require_once(dirname(__FILE__)."/init-admin.php");
    
    $title = 'Users';

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
        SELECT UserID, Username, EntryCode, ut.Type, ut.DisplayName AS UserTypeDisplayName, c.Name AS ClubName, 
                u.LastLoginDate ' . $extraSelect . '
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

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href=".">Back</a></p>

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
    <table class="striped tablesorter-materialize">
        <thead>
            <tr>
                <th data-placeholder="Username">Username</th>
                <th data-placeholder="Entry Code">Entry Code</th>
                <?php if ($isWebAdmin || $isConferenceAdmin) { ?>
                    <th data-placeholder="User Type">User Type</th>
                    <th data-placeholder="Club">Club</th>
                <?php } ?>
                <?php if ($isWebAdmin) { ?>
                    <th data-placeholder="Conference">Conference</th>
                <?php } ?>
                <th data-placeholder="Last Login">Last Login</th>
                <th data-sorter="false" data-filter="false"></th>
                <th data-sorter="false" data-filter="false"></th>
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
                        <td><?= (new DateTime($user["LastLoginDate"]))->format('D, M d, Y') ?></td>
                        <td>
                            <!-- edit this to not let you edit people above you -->
                            <?php if ($_SESSION["UserID"] != $user["UserID"] && $canEdit) { ?> 
                                <a class="waves-effect waves-light btn" href="create-edit-user.php?type=update&id=<?=$user['UserID'] ?>">Edit</a>
                            <?php } ?> 
                        </td>
                        <td>
                            <?php if ($_SESSION["UserID"] != $user["UserID"] && $canEdit) { ?> 
                                <a class="waves-effect waves-light btn red white-text" href="delete-user.php?id=<?=$user['UserID'] ?>">Delete</a>
                            <?php } ?> 
                         </td>
                    </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $(".tablesorter-materialize").tablesorter({ 
            sortList: [[4,0], [3,0], [0,0]] ,
            widgets: ["filter", "stickyHeaders"],

            widgetOptions : {
                filter_placeholder : { search : '', select : '' }
            }
        });
    });
</script>

<?php include(dirname(__FILE__)."/../footer.php"); ?>