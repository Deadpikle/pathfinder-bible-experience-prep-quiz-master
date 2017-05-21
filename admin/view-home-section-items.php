<?php
    require_once(dirname(__FILE__)."/init-admin.php");
    if ($isClubAdmin) {
        header("Location: index.php");
    }
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

<p><a href="view-home-sections.php">Back</a></p>

<h5><?=$sectionName?></h5>

<div class="lines">
    <form action="ajax/add-line.php" method="post">
        <input type="hidden" name="section-id" value="<?= $sectionID ?>"/>
        <div class="input-field col s6 m4">
            <button class="inline btn waves-effect waves-light submit" type="submit" name="action">Add Line</button>
        </div>
    </form>
    <ul class="browser-default">
        <?php 
            $i = 0;
            foreach ($lines as $line) { 
                $isFirstLineItem = FALSE;
                $lineID = $line["LineID"];
                if ($lineID != $lastLineID) {
                    $isFirstLineItem = TRUE;
                    if ($lastLineID !== -1) {
                        echo "</ul>";
                    }
                    $i++;
                    echo "<li>Line $i</li>";
                    echo "<a class='btn btn-flat teal-text' href='create-edit-line-item.php?lineID=$lineID&sectionID=$sectionID&type=create'>add item</a>";
                    echo "<a class='btn btn-flat red white-text' href='delete-line.php?lineID=$lineID&sectionID=$sectionID'>delete line</a>";
                    echo "<ul class='browser-default sortable'>";
                    $lastLineID = $lineID;
                }
                // TODO: function
                if ($line["Text"] != NULL) {
                    $itemID = $line["ItemID"];
                    if ($line["IsLink"]) {
                        $url = $line["URL"];
                        if (strpos($url, 'http://') === false && strpos($url, 'https://') === false) {
                            $url = "http://" . $url;
                        }
                        echo "<li><a href=\"" . $url . "\">" . $line["Text"] . "</a><br>";
                    }
                    else {
                        echo "<li>" . $line["Text"] . "<br>";
                    }
                    echo "<a href='create-edit-line-item.php?lineID=$lineID&sectionID=$sectionID&itemID=$itemID&type=update'>edit</a>";
                    echo "&nbsp;&nbsp;";
                    echo "<a href='delete-line-item.php?itemID=$itemID&sectionID=$sectionID'>delete</a></li>";
                }
            }
        ?>
    </ul>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        sortable('.sortable');
    });
</script>

<?php include(dirname(__FILE__)."/../footer.php"); ?>