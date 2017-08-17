<?php
    require_once(dirname(__FILE__)."/../init-admin.php");
    try {
        $params = [
            trim($_POST["blankable-word"])
        ];
        if ($_GET["type"] == "update") {
            $query = '
                UPDATE BlankableWords SET Word = ? WHERE WordID = ?
            ';
            $params[] = $_POST["word-id"];
        }
        else if ($_GET["type"] == "create") {
            $query = '
                INSERT INTO BlankableWords (Word) VALUES (?)
            ';
        }
        else {
            die("Invalid type");
        }
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        header("Location: $basePath/admin/view-non-blankable-words.php");
    }
    catch (PDOException $e) {
        print_r($e);
        die();
    }
?>