<?php
    require_once(dirname(__FILE__)."/init.php");
    if ($_SESSION["UserType"] !== "WebAdmin") {
        die("You shall not pass!");
    }
    $query = "SELECT QuestionID, Question FROM Questions WHERE Question LIKE '%Â%';";

    $update = "UPDATE Questions SET Question = ? WHERE QuestionID = ?";
    try {
        $questions = $stmt->fetchAll();
        $stmt = $pdo->prepare($query);
        foreach ($questions as $question) {
            $qID = $question["QuestionID"];
            $text = $question["Question"];
            $params = [
                $qID, 
                trim($text)
            ];
            $stmt->execute($params);
        }
    }
    catch (PDOException $e) {
        echo "error inserting question <br>";
        //print_r($e);
        //die();
    }
?>