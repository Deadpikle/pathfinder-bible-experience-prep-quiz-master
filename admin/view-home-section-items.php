<?php
    require_once(dirname(__FILE__)."/init-admin.php");
    
    $title = 'Home Sections';
    
    if ($isClubAdmin) {
        header("Location: index.php");
    }
    $conferenceID = $_GET["conferenceID"];
    $sectionID = $_GET['sectionID'];
    $query = '
        SELECT his.Name AS SectionName,
            hil.Name AS LineName, hil.SortOrder AS LineSortOrder, hil.HomeInfoLineID AS LineID,
            hii.HomeInfoItemID AS ItemID, hii.Text, hii.IsLink, hii.URL, hii.SortOrder AS ItemSortOrder
        FROM HomeInfoSections his 
            JOIN HomeInfoLines hil ON his.HomeInfoSectionID = hil.HomeInfoSectionID
            LEFT JOIN HomeInfoItems hii ON hil.HomeInfoLineID = hii.HomeInfoLineID
        WHERE hil.HomeInfoSectionID = ?
        ORDER BY LineSortOrder, ItemSortOrder';
    $lineStmt = $pdo->prepare($query);
    $lineStmt->execute([$sectionID]);
    $lines = $lineStmt->fetchAll();
    if (count($lines) > 0) {
        $sectionName = $lines[0]["SectionName"];
    }
    else {
        $query = 'SELECT Name FROM HomeInfoSections WHERE HomeInfoSectionID = ?';
        $nameStmt = $pdo->prepare($query);
        $nameStmt->execute([$sectionID]);
        $row = $nameStmt->fetch(); 
        $sectionName = $row["Name"];
    }
    $lastLineID = -1;
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href="view-home-sections.php?conferenceID=<?= $conferenceID ?>">Back</a></p>

<h5><?=$sectionName?></h5>

<div class="lines">
    <form action="ajax/add-line.php" method="post">
        <input type="hidden" name="conference-id" value="<?= $conferenceID ?>">
        <input type="hidden" name="section-id" value="<?= $sectionID ?>"/>
        <div class="input-field col s6 m4">
            <button class="inline btn waves-effect waves-light submit" type="submit" name="action">Add Line</button>
        </div>
    </form>
    <p>You can drag and drop lines and line items to resort them.</p>
    <a id="save-sort" class="btn btn-flat teal-text">Save Sorted Items</a>
    <ul class="browser-default sortable">
        <?php 
            $i = 0;
            foreach ($lines as $line) { 
                $isFirstLineItem = FALSE;
                $lineID = $line["LineID"];
                if ($lineID != $lastLineID) {
                    $isFirstLineItem = TRUE;
                    if ($lastLineID !== -1) {
                        echo "</li></ul>";
                    }
                    $i++;
                    echo "<li class='line' id='line-id-$lineID'>Line $i<br>";
                    echo "<a class='btn btn-flat teal-text' href='create-edit-line-item.php?lineID=$lineID&sectionID=$sectionID&type=create&conferenceID=$conferenceID'>add item</a>";
                    echo "<a class='btn btn-flat red white-text' href='delete-line.php?lineID=$lineID&sectionID=$sectionID&conferenceID=$conferenceID'>delete line</a>";
                    echo "<ul class='browser-default sortable'>";
                    $lastLineID = $lineID;
                }
                if ($line["Text"] != NULL) {
                    $itemID = $line["ItemID"];
                    if ($line["IsLink"]) {
                        $url = $line["URL"];
                        if (strpos($url, 'http://') === false && strpos($url, 'https://') === false) {
                            $url = "http://" . $url;
                        }
                        echo "<li class='line-item' id='item-id-$itemID'><a href=\"" . $url . "\">" . $line["Text"] . "</a><br>";
                    }
                    else {
                        echo "<li class='line-item' id='item-id-$itemID'>" . $line["Text"] . "<br>";
                    }
                    echo "<a href='create-edit-line-item.php?lineID=$lineID&sectionID=$sectionID&itemID=$itemID&type=update&conferenceID=$conferenceID'>edit</a>";
                    echo "&nbsp;&nbsp;";
                    echo "<a href='delete-line-item.php?itemID=$itemID&sectionID=$sectionID&conferenceID=$conferenceID'>delete</a></li>";
                }
            }
        ?>
    </ul>
</div>

<div id="saved-modal" class="modal">
    <div class="modal-content">
        <h4>Line order saved!</h4>
    </div>
    <div class="modal-footer">
        <a href="#!" class="modal-action modal-close waves-effect waves-teal teal-text btn-flat">OK</a>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $('#saved-modal').modal();
        sortable('.sortable', {
            forcePlaceholderSize: true,
            placeholderClass: 'teal lighten-5',
        });
        $('#save-sort').on("click",function() {
            var lines = [];
            $('.line').each(function(index, element) {
                console.log(element.id);
                var items = [];
                $(element).find('.line-item').each(function(lineIndex, lineElement) {
                    items.push({
                        id: lineElement.id.replace('item-id-', ''),
                        index: lineIndex
                    });
                });
                var lineObj = {
                    id: element.id.replace('line-id-', ''),
                    index: index,
                    items: items 
                };
                lines.push(lineObj)
            });
            $.ajax({
                type: "POST",
                url: "ajax/save-line-sorting.php",
                data: {
                    json: JSON.stringify(lines)
                },
                success: function(msg) {
                    $('#saved-modal').modal('open');
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    alert(thrownError);
                }
            });
        });
    });
</script>

<?php include(dirname(__FILE__)."/../footer.php"); ?>