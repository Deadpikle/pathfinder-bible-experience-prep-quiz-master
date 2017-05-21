<?php

// TODO:
// Error messages if server fails

    require_once(dirname(__FILE__)."/init-admin.php");

    if ($isClubAdmin) {
        die("invalid user type");
    }
    $sectionID = $_GET["sectionID"];
    if ($_GET["type"] == "update") {
        $query = '
            SELECT Text, IsLink, URL, SortOrder
            FROM HomeInfoItemIDs hii
            WHERE HomeInfoItemID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET["id"]]);
        $section = $stmt->fetch();
        if ($section == NULL) {
            die("invalid line id");
        }
        $lineID = $_GET["lineID"];
        $isLink = $line["IsLink"] ? "checked" : "";
        $text = "";
        $URL = "";
        $sortOrder = -1;
        $postType = "update";
    }
    else {
        $lineID = "";
        $isLink = "";
        $text = "";
        $URL = "";
        $sortOrder = -1;
        $postType = "create";
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href="./view-home-section-items.php?sectionID=<?=$sectionID?>">Back</a></p>

<div id="edit-user">
    <form action="ajax/save-line-edits.php?type=<?= $postType ?>" method="post">
        <input type="hidden" name="line-id" value="<?= $lineID ?>"/>
        <div class="row">
            <div class="input-field col s12 m4">
                <input type="text" id="line-text" name="line-text" value="<?= $text ?>" required/>
                <label for="line-text">Item Text</label>
            </div>
            <div class="input-field col s12 m2">
                <input type="checkbox" id="line-is-link" name="line-is-link" <?= $isLink ?>/>
                <label for="line-is-link">Links to URL</label>
            </div>
            <div class="input-field col s12 m6">
                <input type="url" id="line-url" name="line-url" value="<?= $URL ?>"/>
                <label for="line-url">URL</label>
            </div>
        </div>
        <button class="btn waves-effect waves-light submit" type="submit" name="action">Save</button>
    </form>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        // http://stackoverflow.com/a/11967638/3938401
        // http://stackoverflow.com/a/19166712/3938401 -- how to actually change required property
        $('#line-is-link').change(function () {
            if ($(this).is(':checked')) {
                $('#line-url').prop('required', true);
            } 
            else {
                $('#line-url').prop('required', false);
            }
        });
    });
</script>

<?php include(dirname(__FILE__)."/../footer.php"); ?>