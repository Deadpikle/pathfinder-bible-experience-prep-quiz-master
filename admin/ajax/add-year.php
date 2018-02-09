<?php
    require_once(dirname(__FILE__)."/../init-admin.php");
    try {
        $yearNumber = $_POST["year"];
        if (!is_numeric($yearNumber)) {
            header("Location: $basePath/admin/view-years.php");
            die();
        }
        $query = 'SELECT 1 FROM Years WHERE Year = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([intval($yearNumber)]);
        $yearData = $stmt->fetchAll();
        if ($yearData === false || count($yearData) > 0) {
            // year already exists; don't add it!
            header("Location: $basePath/admin/view-years.php");
            die();
        }
        $params = [
            intval($yearNumber), 
            0
        ];
        $query = '
            INSERT INTO Years (Year, IsCurrent) VALUES (?, ?)
        ';
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        header("Location: $basePath/admin/view-years.php");
    }
    catch (PDOException $e) {
        print_r($e);
        die();
    }
?>