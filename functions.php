<?php

    function generate_uuid() {
        $bytes = random_bytes(16);
        $UUID = bin2hex($bytes);
        // yay for laziness on the hyphen inserts! code from https://stackoverflow.com/a/33484855/3938401
        $UUID = substr($UUID, 0, 8) . '-' . 
                substr($UUID, 8, 4) . '-' . 
                substr($UUID, 12, 4) . '-' . 
                substr($UUID, 16, 4)  . '-' . 
                substr($UUID, 20);
        return $UUID;
    }

    function load_home_sections($pdo) {
        $query = '
        SELECT his.HomeInfoSectionID AS SectionID, his.Name AS SectionName, his.SortOrder AS SectionSortOrder,
            hil.HomeInfoLineID AS LineID,
            hii.HomeInfoItemID, hii.Text, hii.IsLink, hii.URL, hii.SortOrder AS ItemSortOrder
        FROM HomeInfoSections his 
            LEFT JOIN HomeInfoLines hil ON his.HomeInfoSectionID = hil.HomeInfoSectionID
            LEFT JOIN HomeInfoItems hii ON hil.HomeInfoLineID = hii.HomeInfoLineID
        ORDER BY SectionSortOrder, hil.SortOrder, ItemSortOrder';
        $sectionStmt = $pdo->prepare($query);
        $sectionStmt->execute([]); // will we ever need params here?
        $sections = $sectionStmt->fetchAll();
        return $sections;
    }

    function output_home_sections($sections, $isAdminPage) {
        $lastSectionID = -1;
        $lastLineID = -1;
        foreach ($sections as $section) { 
            $sectionID = $section["SectionID"];
            $lineID = $section["LineID"];
            if ($lastSectionID !== $sectionID) {
                if ($lastSectionID !== -1) {
                    echo "</div></ul>";
                }
                $lastSectionID = $sectionID;
                echo "<div class='sortable-item' id='section-$lastSectionID'>";
                echo "<h5>" . $section["SectionName"] . "</h5>";
                $extraULClass = "";
                if ($isAdminPage) {
                    $extraULClass = "browser-default";
                    echo "<div class='section-buttons'>";
                        echo "<div class='row'>";
                            echo "<a class='add waves-effect waves-teal btn-flat teal-text col s12 m2 center-align' href='create-edit-section.php?type=update&id=$sectionID'>Edit Section Name</a>";
                            echo "<a class='add waves-effect waves-teal btn-flat teal-text col s12 m2 center-align' href='view-home-section-items.php?sectionID=$sectionID'>Edit Line Items</a>";
                            echo "<a class='add waves-effect waves-teal btn-flat red white-text col s12 m2 center-align' href='delete-section.php?id=$sectionID'>Delete Section</a>";
                        echo "</div>";
                    echo "</div>";
                }
                echo "<ul class='section-items $extraULClass'>";
            }
            if ($section["Text"] != NULL) {
                $isFirstLineItem = FALSE;
                if ($lastLineID !== $lineID) {
                    $isFirstLineItem = TRUE;
                    if ($lastLineID !== -1) {
                        echo "</li>";
                    }
                    $lastLineID = $lineID;
                    echo "<li>";
                }
                if (!$isFirstLineItem) {
                    echo " - ";
                }
                if ($section["IsLink"]) {
                    $url = $section["URL"];
                    if (strpos($url, 'http://') === false && strpos($url, 'https://') === false) {
                        $url = "http://" . $url;
                    }
                    echo "<a href=\"" . $url . "\">" . $section["Text"] . "</a>";
                }
                else {
                    echo $section["Text"];
                }
            }
            else {
                // make sure we finish off the last line item
                if ($lastLineID !== -1) {
                    echo "</li>";
                }
                $lastLineID = -1;
            }
        }
        if ($lastLineID !== -1) {
            echo "</li>";
        }
        if ($lastSectionID !== -1) {
            echo "</ul>";
        }
        echo "</div>";
    }

?>