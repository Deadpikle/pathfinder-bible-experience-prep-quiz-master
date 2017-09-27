<?php
    // DO NOT RUN THIS FILE UNLESS YOU KNOW WHAT YOU'RE DOING
    require_once(dirname(__FILE__)."/init.php");
    die();
    echo("Creating verse content...<br>");
    $chaptersQuery = "
        SELECT ChapterID, NumberVerses
        FROM Chapters";
    $chapters = $pdo->query($chaptersQuery)->fetchAll();
    $insertQuery = " INSERT INTO Verses (Number, VerseText, ChapterID) VALUES (?, ?, ?)";
    $insertStmt = $pdo->prepare($insertQuery);
    foreach ($chapters as $chapter) { 
        for ($i = 0; $i < $chapter["NumberVerses"]; $i++) {
            $params = [
                ($i+1),
                "",
                $chapter["ChapterID"]
            ];
            $insertStmt->execute($params);
        }
    }
?>