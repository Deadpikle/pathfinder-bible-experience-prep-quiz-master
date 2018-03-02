<?php
    require_once(dirname(__FILE__)."/../init-admin.php");
    try {
        $commentaryNumber = $_POST["commentary"];
        $topic = filter_var($_POST["topic"], FILTER_SANITIZE_STRING);
        if (!is_numeric($commentaryNumber)) {
            header("Location: $basePath/admin/view-years.php");
            die();
        }
        $query = 'SELECT 1 FROM Commentaries WHERE Number = ? AND TopicName = ? AND YearID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([intval($commentaryNumber), $topic, $activeYearID]);
        $commentaryData = $stmt->fetchAll();
        if ($commentaryData === false || count($commentaryData) > 0) {
            // commentary already exists; don't add it!
            header("Location: $basePath/admin/view-commentaries.php");
            die();
        }
        $params = [
            intval($commentaryNumber), 
            $topic,
            $activeYearID
        ];
        $query = '
            INSERT INTO Commentaries (Number, TopicName, YearID) VALUES (?, ?, ?)
        ';
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        header("Location: $basePath/admin/view-commentaries.php");
    }
    catch (PDOException $e) {
        print_r($e);
        die();
    }
?>