<?php

// TODO:
// Error messages if server fails

    require_once(dirname(__FILE__)."/init-admin.php");

    if ($isClubAdmin) {
        die("invalid user type");
    }
    $conferenceID = $_GET["conferenceID"];
    $sectionID = $_GET["sectionID"];
    $lineID = $_GET["lineID"];
    if ($_GET["type"] == "update") {
        $itemID = $_GET["itemID"];
        $query = '
            SELECT HomeInfoItemID, Text, IsLink, URL, SortOrder
            FROM HomeInfoItems hii
            WHERE HomeInfoItemID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$itemID]);
        $lineItem = $stmt->fetch();
        if ($lineItem == null) {
            die("invalid line id");
        }
        $itemID = $lineItem["HomeInfoItemID"];
        $isLink = $lineItem["IsLink"] ? "checked" : "";
        $text = $lineItem["Text"];
        $URL = $lineItem["URL"];
        $sortOrder = $lineItem["SortOrder"];
        $postType = "update";
        $titleString = "Edit";
    }
    else {
        $itemID = "";
        $isLink = "";
        $text = "";
        $URL = "";
        $sortOrder = -1;
        $postType = "create";
        $titleString = "Create";
    }
    
    $title = $titleString . ' Line';

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p>
    <a class="btn-flat blue-text waves-effect waves-blue no-uppercase" 
      href="./view-home-section-items.php?sectionID=<?= $sectionID ?>&conferenceID=<?= $conferenceID ?>">Back</a>
</p>

<h4><?= $titleString ?> Line Item</h4>

<div id="edit-line-item">
    <form action="ajax/save-line-item-edits.php?type=<?= $postType ?>" method="post">
        <input type="hidden" name="conference-id" value="<?= $conferenceID ?>">
        <input type="hidden" name="section-id" value="<?= $sectionID ?>"/>
        <input type="hidden" name="line-id" value="<?= $lineID ?>"/>
        <input type="hidden" name="item-id" value="<?= $itemID ?>"/>
        <div class="row">
            <div class="input-field col s12 m4">
                <input type="text" id="line-text" name="line-text" value="<?= $text ?>" required/>
                <label for="line-text">Item Text</label>
            </div>
            <div class="input-field col s12 m2">
                <input type="checkbox" id="line-is-link" name="line-is-link" <?= $isLink ?>/>
                <label class="black-text" for="line-is-link">Links to URL</label>
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