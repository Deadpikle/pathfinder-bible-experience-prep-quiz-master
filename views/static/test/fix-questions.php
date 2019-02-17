<?php
    require_once(dirname(__FILE__)."/init.php");
    if ($_SESSION["UserType"] !== "WebAdmin") {
        die("You shall not pass!");
    }
    $query = "SELECT QuestionID, Question FROM Questions WHERE Question LIKE '%Â%';";

    $update = "UPDATE Questions SET Question = ? WHERE QuestionID = ?";
    $count = 0;
    try {
        $qStatement = $pdo->prepare($query);
        $qStatement->execute([]);
        $questions = $qStatement->fetchAll();
        $updateStmnt = $pdo->prepare($update);
        foreach ($questions as $question) {
            $qID = $question["QuestionID"];
            $text = $question["Question"];
            $text = str_replace("Â", " ", $text);
            $text = preg_replace("/\xC2\xA0/", ' ', $text);
            $params = [
                $qID, 
                trim($text)
            ];
            $updateStmnt->execute($params);
            $count += 1;
        }
    }
    catch (PDOException $e) {
        echo "error inserting question <br>";
        //print_r($e);
        die();
    }
    echo("got it " . $count);
?>