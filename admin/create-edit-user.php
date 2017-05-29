<?php

// TODO:
// Error messages if server fails

    require_once(dirname(__FILE__)."/init-admin.php");

    if ($_GET["type"] == "update") {
        $query = '
            SELECT UserID, Username, ut.UserTypeID AS UserType, c.ClubID AS ClubID
            FROM Users u JOIN UserTypes ut ON u.UserTypeID = ut.UserTypeID
                LEFT JOIN Clubs c ON u.ClubID = c.ClubID
            WHERE UserID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET["id"]]);
        $user = $stmt->fetch();
        if ($user == NULL) {
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
        $clubsQuery = 'SELECT ClubID, Name FROM Clubs ORDER BY Name';
        $clubs = $pdo->query($clubsQuery)->fetchAll();
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href="./view-users.php">Back</a></p>

<h4><?= $titleString ?> User</h4>

<div id="edit-user">
    <form action="ajax/save-user-edits.php?type=<?= $postType ?>" method="post">
        <input type="hidden" name="user-id" value="<?= $userID ?>"/>
        <div class="row">
            <div class="input-field col s12 m4">
                <input type="text" id="username" name="username" value="<?= $username ?>" required data-length="50"/>
                <label for="first-name">Username</label>
            </div>
        </div>
        <p>Usernames are not used for logging into the website; however, it is used as an easy way to distinguish between different Pathfinders in your club. Users are greeted by their username on the home page of this website. In order to help follow the <a href="https://en.wikipedia.org/wiki/Children%27s_Online_Privacy_Protection_Act">Children's Online Privacy Protection Act</a> for children younger than 13, please do not use real names when choosing a username for your Pathfinder. We don't collect any data on users for our website, but let's all play it safe and avoid real names! Suggested names: 'Pathfinder #37', 'Secret Agent #08', etc.</p>
        <?php if ($isWebAdmin) { ?>
            <div class="row">
                <div class="input-field col s12 m4">
                    <select id="club" name="club" required>
                        <option id="club-no-selection-option" value="">Select a club...</option>
                        <?php foreach ($clubs as $club) { 
                                $selected = "";
                                if ($club['ClubID'] == $clubID)
                                    $selected = "selected";
                        ?>
                            <option value="<?= $club['ClubID'] ?>" <?=$selected?> ><?=$club['Name']?></option>
                        <?php } ?>
                    </select>
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
                </div>
            </div>
        <?php } ?>
        <button class="btn waves-effect waves-light submit" type="submit" name="action">Save</button>
    </form>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $('select').material_select();
        // https://github.com/Dogfalo/materialize/issues/1861 fix validate on material selectors
        $('select[required]').css({
            display: 'inline',
            position: 'absolute',
            float: 'left',
            padding: 0,
            margin: 0,
            border: '1px solid rgba(255,255,255,0)',
            height: 0, 
            width: 0,
            top: '2em',
            left: '3em'
        });
    });
</script>

<?php include(dirname(__FILE__)."/../footer.php"); ?>