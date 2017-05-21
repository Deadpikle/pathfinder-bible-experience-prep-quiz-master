<?php

// TODO:
// Require dropdowns (unsupported in safari?)
// Error messages if server fails
// Server needs to generate entry code on submit of new user
// auto-select dropdown if editing user
// on save, if club admin, auto set club ID and user type ID

    require_once(dirname(__FILE__)."/init-admin.php");

    if ($_GET["type"] == "update") {
        $query = '
            SELECT UserID, FirstName, LastName, ut.UserTypeID AS UserType, c.ClubID AS ClubID
            FROM Users u JOIN UserTypes ut ON u.UserTypeID = ut.UserTypeID
                LEFT JOIN Clubs c ON u.ClubID = c.ClubID
            WHERE UserID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET["id"]]);
        $user = $stmt->fetch();
        $userID = $user["UserID"];
        $firstName = $user["FirstName"];
        $lastName = $user["LastName"];
        $entryCode = $user["EntryCode"];
        $userTypeID = $user["UserType"];
        $clubID = $user["ClubID"];
        $postType = "update";
    }
    else {
        $userID = "";
        $firstName = "";
        $lastName = "";
        $entryCode = "";
        $userTypeID = -1;
        $clubID = -1;
        $postType = "create";
    }

    $isWebAdmin = $_SESSION["UserType"] === "WebAdmin";
    if ($isWebAdmin) {
        $userTypesQuery = 'SELECT UserTypeID, DisplayName FROM UserTypes ORDER BY UserTypeID';
        $userTypes = $pdo->query($userTypesQuery)->fetchAll();
        $clubsQuery = 'SELECT ClubID, Name FROM Clubs ORDER BY Name';
        $clubs = $pdo->query($clubsQuery)->fetchAll();
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href="./view-users.php">Back</a></p>

<div id="edit-user">
    <form action="ajax/save-user-edits.php?type=<?= $postType ?>" method="post">
        <input type="hidden" name="user-id" value="<?= $userID ?>"/>
        <div class="row">
            <div class="input-field col s12 m4">
                <input type="text" id="first-name" name="first-name" value="<?= $firstName ?>" required/>
                <label for="first-name">First Name</label>
            </div>
            <div class="input-field col s12 m4">
                <input type="text" id="last-name" name="last-name" value="<?= $lastName ?>" required/>
                <label for="last-name">Last Name</label>
            </div>
        </div>
        <?php if ($isWebAdmin) { ?>
            <div class="row">
                <div class="input-field col s12 m4">
                    <select id="club-select" name="club" required>
                        <option id="club-no-selection-option" value="">Select a club...</option>
                        <?php foreach ($clubs as $club) { ?>
                            <option value="<?= $club['ClubID'] ?>"><?=$club['Name']?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <select class="col s4" id="user-type-select" name="user-type" required>
                    <option id="user-type-no-selection-option" value="">Select a user type...</option>
                    <?php foreach ($userTypes as $userType) { ?>
                        <option value="<?= $userType['UserTypeID'] ?>"><?=$userType['DisplayName']?></option>
                    <?php } ?>
                </select>
            </div>
        <?php } ?>
        <button class="btn waves-effect waves-light submit" type="submit" name="action">Save</button>
    </form>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $('select').material_select();
    });
</script>

<?php include(dirname(__FILE__)."/../footer.php"); ?>