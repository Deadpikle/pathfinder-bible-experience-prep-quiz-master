<?php
    require_once(dirname(__FILE__)."/../init-admin.php");
    try {
        $returnHeader = "Location: $basePath/admin/view-books.php";
        $bookName = $_POST["name"];
        $numberChapters = $_POST["number-chapters"];
        if (strlen($bookName) < 1) {
            header($returnHeader);
            die();
        }
        if (!is_numeric($numberChapters)) {
            header($returnHeader);
            die();
        }
        $query = 'SELECT 1 FROM Books WHERE Name = ? AND YearID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$bookName, $activeYearID]);
        $bookData = $stmt->fetchAll();
        if ($bookData === false || count($bookData) > 0) {
            // book already exists; don't add it!
            header($returnHeader);
            die();
        }
        $params = [
            trim($bookName),
            intval($numberChapters), 
            $activeYearID
        ];
        $query = '
            INSERT INTO Books (Name, NumberChapters, YearID) VALUES (?, ?, ?)
        ';
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        header($returnHeader);
    }
    catch (PDOException $e) {
        print_r($e);
        die();
    }
?>