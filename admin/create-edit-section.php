<?php

// TODO:
// Error messages if server fails

    require_once(dirname(__FILE__)."/init-admin.php");

    if ($isClubAdmin) {
        die("invalid user type");
    }

    if ($_GET["type"] == "update") {
        $query = '
            SELECT Name, SortOrder
            FROM HomeInfoSections his
            WHERE HomeInfoSectionID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET["id"]]);
        $section = $stmt->fetch();
        if ($section == NULL) {
            die("invalid section id"); // TODO: better error
        }
        $sectionID = $_GET["id"];
        $sectionName = $section["Name"];
        $sortOrder = $section["SortOrder"];
        $postType = "update";
    }
    else {
        $sectionID = "";
        $sectionName = "";
        $sortOrder = -1;
        $postType = "create";
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href="./view-home-sections.php">Back</a></p>

<div id="edit-user">
    <form action="ajax/save-section-edits.php?type=<?= $postType ?>" method="post">
        <input type="hidden" name="section-id" value="<?= $sectionID ?>"/>
        <div class="row">
            <div class="input-field col s12 m4">
                <input type="text" id="section-name" name="section-name" value="<?= $sectionName ?>" required/>
                <label for="section-name">Section Name</label>
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