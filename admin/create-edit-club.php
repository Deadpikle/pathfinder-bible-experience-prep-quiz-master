<?php

// TODO:
// Error messages if server fails

    require_once(dirname(__FILE__)."/init-admin.php");

    if ($isClubAdmin) {
        die("invalid user type");
    }

    if ($_GET["type"] == "update") {
        $query = '
            SELECT ClubID, Name, URL, ConferenceID
            FROM Clubs
            WHERE ClubID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET["id"]]);
        $club = $stmt->fetch();
        if ($club == NULL) {
            die("invalid club id"); // TODO: better error
        }
        $clubID = $_GET["id"];
        $conferenceID = $club["ConferenceID"];
        $clubName = $club["Name"];
        $url = $club["URL"];
        $postType = "update";
        $titleString = "Edit";
    }
    else {
        $clubID = "";
        $conferenceID = -1;
        $clubName = "";
        $url = "";
        $postType = "create";
        $titleString = "Create";
    }
    if ($isWebAdmin) {
        // need to let user choose which conference the club belongs to
        $query = 'SELECT ConferenceID, Name FROM Conferences ORDER BY Name';
        $stmt = $pdo->prepare($query);
        $stmt->execute([]);
        $conferences = $stmt->fetchAll();
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href="./view-clubs.php">Back</a></p>

<h4><?= $titleString ?> Pathfinder Club</h4>

<div id="edit-club">
    <form action="ajax/save-club-edits.php?type=<?= $postType ?>" method="post">
        <input type="hidden" name="club-id" value="<?= $clubID ?>"/>
        <div class="row">
            <div class="input-field col s12 m4">
                <input type="text" id="club-name" name="club-name" value="<?= $clubName ?>" required data-length="150"/>
                <label for="club-name">Club Name</label>
            </div>
            <div class="input-field col s12 m4">
                <input type="url" id="club-url" name="club-url" value="<?= $url ?>" data-length="300"/>
                <label for="club-url">Website or Facebook URL</label>
            </div>
        </div>
        <div class="row">
            <?php if ($isWebAdmin) { ?>
                <div class="input-field col s12 m4">
                    <select id="conference" name="conference" required>
                        <option id="conference-no-selection-option" value="">Select a conference...</option>
                        <?php foreach ($conferences as $conference) { 
                                $selected = "";
                                if ($conference['ConferenceID'] == $conferenceID) {
                                    $selected = "selected";
                                }
                        ?>
                            <option value="<?= $conference['ConferenceID'] ?>" <?= $selected ?>><?=$conference['Name']?></option>
                        <?php } ?>
                    </select>
                    <label>Conference</label>
                </div>
            <?php } ?>
            <div class="input-field col s12 m4">
                <button class="btn waves-effect waves-light submit" type="submit" name="action">Save</button>
            </div>
        </div>
    </form>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $('select').material_select();
        fixRequiredSelectorCSS();
    });
</script>

<?php include(dirname(__FILE__)."/../footer.php"); ?>