<?php

// TODO:
// Error messages if server fails

    require_once(dirname(__FILE__)."/init-admin.php");

    if (!$isAdmin) {
        header("Location: $basePath/index.php");
        die();
    }
    if ($_GET["type"] == "update") {
        $extraWhere = '';
        $query = '
            SELECT UserID, Username, ut.UserTypeID AS UserType, c.ClubID AS ClubID
            FROM Users u JOIN UserTypes ut ON u.UserTypeID = ut.UserTypeID
                LEFT JOIN Clubs c ON u.ClubID = c.ClubID
            WHERE UserID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET["id"]]);
        $user = $stmt->fetch();
        if ($user == null) {
            die("invalid user id"); // TODO: better error
        }
        $userID = $user["UserID"];
        $username = $user["Username"];
        $userTypeID = $user["UserType"];
        $clubID = $user["ClubID"];
        $postType = "update";
        $titleString = "Edit";
    }
    else {
        $userID = "";
        $username = "";
        $entryCode = "";
        $userTypeID = -1;
        $clubID = -1;
        $postType = "create";
        $titleString = "Create";
    }

    if ($isWebAdmin) {
        $userTypesQuery = 'SELECT UserTypeID, DisplayName FROM UserTypes ORDER BY UserTypeID';
        $userTypes = $pdo->query($userTypesQuery)->fetchAll();
        $clubsQuery = '
            SELECT ClubID, c.Name AS ClubName, conf.Name AS ConferenceName
            FROM Clubs c LEFT JOIN Conferences conf ON c.ConferenceID = conf.ConferenceID
            ORDER BY conf.Name, c.Name';
        $clubs = $pdo->query($clubsQuery)->fetchAll();
    }
    else if ($isConferenceAdmin) {
        $userTypesQuery = "
            SELECT UserTypeID, Type, DisplayName 
            FROM UserTypes
            WHERE Type <> 'WebAdmin' AND Type <> 'ConferenceAdmin' 
            ORDER BY UserTypeID";
        $userTypes = $pdo->query($userTypesQuery)->fetchAll();
        $clubsQuery = '
            SELECT ClubID, Name AS ClubName
            FROM Clubs 
            WHERE ConferenceID = ?
            ORDER BY ClubName';
        $params = [ $_SESSION["ConferenceID"] ];
        $stmt = $pdo->prepare($clubsQuery);
        $stmt->execute($params);
        $clubs = $stmt->fetchAll();
    }
    
    $title = $titleString . ' User';

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href="./view-users.php">Back</a></p>

<h4><?= $titleString ?> User</h4>

<div id="edit-user">
    <form action="ajax/save-user-edits.php?type=<?= $postType ?>" method="post">
        <input type="hidden" name="user-id" value="<?= $userID ?>"/>
        <div class="row">
            <div class="input-field col s12 m4">
                <input type="text" id="username" name="username" value="<?= $username ?>" required data-length="150"/>
                <label for="first-name">Username</label>
            </div>
        </div>
        <p>Usernames are not used for logging into the website; however, it is used as an easy way to distinguish between different Pathfinders in your club. Users are greeted by their username on the home page of this website. In order to help follow the <a href="https://en.wikipedia.org/wiki/Children%27s_Online_Privacy_Protection_Act">Children's Online Privacy Protection Act</a> for children younger than 13, please do not use real names when choosing a username for your Pathfinder. We don't collect any personal data on users for our website (e.g. birthday, phone number, etc.), but let's all play it safe and avoid real names! Suggested names: 'Pathfinder #37', 'Secret Agent #08', etc.</p>
        <?php if ($isWebAdmin || $isConferenceAdmin) { ?>
            <div class="row">
                <div class="input-field col s12 m4">
                    <select id="club" name="club" required>
                        <option id="club-no-selection-option" value="">Select a club...</option>
                        <?php foreach ($clubs as $club) { 
                                $displayName = $club['ClubName'];
                                if ($isWebAdmin) {
                                    $displayName .= ' (' . $club['ConferenceName'] . ')';
                                }
                                $selected = "";
                                if ($club['ClubID'] == $clubID)
                                    $selected = "selected";
                        ?>
                            <option value="<?= $club['ClubID'] ?>" <?=$selected?> ><?=$displayName?></option>
                        <?php } ?>
                    </select>
                    <label>Club</label>
                </div>
                <div class="input-field col s12 m4">
                    <select id="user-type" name="user-type" required>
                        <option id="user-type-no-selection-option" value="">Select a user type...</option>
                        <?php 
                            foreach ($userTypes as $userType) { 
                                $selected = "";
                                if ($userType['UserTypeID'] == $userTypeID)
                                    $selected = "selected";
                        ?>
                            <option value="<?= $userType['UserTypeID'] ?>" <?=$selected?> ><?=$userType['DisplayName']?></option>
                        <?php } ?>
                    </select>
                    <label>User Type</label>
                </div>
            </div>
        <?php } ?>
        <button class="btn waves-effect waves-light submit" type="submit" name="action">Save</button>
    </form>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $('select').material_select();
        fixRequiredSelectorCSS();
    });
</script>

<?php include(dirname(__FILE__)."/../footer.php"); ?>