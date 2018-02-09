<?php
    require_once(dirname(__FILE__)."/../init-admin.php");
    try {
        $bookID = $_POST["book-id"];
        $returnHeader = "Location: $basePath/admin/view-book-details.php?id=" . $bookID;
        $chapterNumber = $_POST["chapter-number"];
        $numberOfVerses = $_POST["number-verses"];
        if (!is_numeric($chapterNumber) || !is_numeric($numberOfVerses) || !is_numeric($bookID)) {
            header($returnHeader);
            die();
        }
        $query = 'SELECT 1 FROM Chapters WHERE Number = ? AND NumberVerses = ? AND BookID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$chapterNumber, $numberOfVerses, $bookID]);
        $chapterData = $stmt->fetchAll();
        if ($chapterData === false || count($chapterData) > 0) {
            // chapter already exists; don't add it!
            header($returnHeader);
            die();
        }
        $params = [
            intval($chapterNumber),
            intval($numberOfVerses), 
            intval($bookID)
        ];
        $query = '
            INSERT INTO Chapters (Number, NumberVerses, BookID) VALUES (?, ?, ?)
        ';
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        // now insert verses into the db for that chapter based on the number of verses
        $chapterID = $pdo->lastInsertId();
        $insertQuery = " INSERT INTO Verses (Number, VerseText, ChapterID) VALUES (?, ?, ?)";
        $insertStmt = $pdo->prepare($insertQuery);
        for ($i = 0; $i < $numberOfVerses; $i++) {
            $params = [
                ($i+1),
                "",
                $chapterID
            ];
            $insertStmt->execute($params);
        }
        // all done!
        header($returnHeader);
    }
    catch (PDOException $e) {
        print_r($e);
        die();
    }
?>