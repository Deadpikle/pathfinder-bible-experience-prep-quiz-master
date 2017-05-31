<?php

// TODO:
// Error messages if server fails

    require_once(dirname(__FILE__)."/init-admin.php");

    if ($isClubAdmin) {
        die("invalid user type");
    }

    if ($_GET["type"] == "update") {
        $query = '
            SELECT ClubID, Name, URL
            FROM Clubs
            WHERE ClubID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET["id"]]);
        $club = $stmt->fetch();
        if ($club == NULL) {
            die("invalid club id"); // TODO: better error
        }
        $clubID = $_GET["id"];
        $clubName = $club["Name"];
        $url = $club["URL"];
        $postType = "update";
        $titleString = "Edit";
    }
    else {
        $clubID = "";
        $clubName = "";
        $url = "";
        $postType = "create";
        $titleString = "Create";
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href="./view-clubs.php">Back</a></p>

<h4><?= $titleString ?> Line Item</h4>

<div id="edit-club">
    <form action="ajax/save-club-edits.php?type=<?= $postType ?>" method="post">
        <input type="hidden" name="club-id" value="<?= $clubID ?>"/>
        <div class="row">
            <div class="input-field col s12 m4">
                <input type="text" id="club-name" name="club-name" value="<?= $clubName ?>" required data-length="100"/>
                <label for="club-name">Club Name</label>
            </div>
            <div class="input-field col s12 m4">
                <input type="url" id="club-url" name="club-url" value="<?= $url ?>" data-length="1000"/>
                <label for="club-url">Website or Facebook URL</label>
            </div>
        </div>
        <button class="btn waves-effect waves-light submit" type="submit" name="action">Save</button>
    </form>
</div>

<script type="text/javascript">
    $(document).ready(function() {
    });
</script>

<?php include(dirname(__FILE__)."/../footer.php"); ?>