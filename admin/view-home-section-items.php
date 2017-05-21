<?php
    require_once(dirname(__FILE__)."/init-admin.php");
    if ($isClubAdmin) {
        header("Location: index.php");
    }
    $query = '
        SELECT 
            hil.Name AS LineName, hil.SortOrder AS LineSortOrder, hil.HomeInfoLineID AS LineID,
            hii.HomeInfoItemID, hii.Text, hii.IsLink, hii.URL, hii.SortOrder AS ItemSortOrder
        FROM HomeInfoLines hil
            JOIN HomeInfoItems hii ON hil.HomeInfoLineID = hii.HomeInfoLineID
        WHERE hil.HomeInfoSectionID = ?
        ORDER BY LineSortOrder, ItemSortOrder';
    $lineStmt = $pdo->prepare($query);
    $lineStmt->execute([$_GET['sectionID']]);
    $lines = $lineStmt->fetchAll();
    $lastLineID = -1;
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href="view-home-sections.php">Back</a></p>

<div class="lines">
    <ul>
        <?php 
            foreach ($lines as $line) { 
                $isFirstLineItem = FALSE;
                if ($line["LineID"] != $lastLineID) {
                    $isFirstLineItem = TRUE;
                    if ($lastLineID !== -1) {
                        echo "</li>";
                    }
                    echo "<li>";
                    $lastLineID = $line["LineID"];
                }

                if (!$isFirstLineItem) {
                    echo " - ";
                }
                // TODO: function
                if ($line["IsLink"]) {
                    $url = $line["URL"];
                    if (strpos($url, 'http://') === false && strpos($url, 'https://') === false) {
                        $url = "http://" . $url;
                    }
                    echo "<a href=\"" . $url . "\">" . $line["Text"] . "</a>";
                }
                else {
                    echo $line["Text"];
                }
            }
        ?>
    </ul>
</div>


<?php include(dirname(__FILE__)."/../footer.php"); ?>