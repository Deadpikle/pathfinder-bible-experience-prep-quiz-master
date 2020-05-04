<?php

namespace App\Models;

use PDO;

class UserAnswer
{
    public $userAnswerID;
    public $answer;
    public $dateAnswered;
    public $wasCorrect;
    
    public $questionID;
    public $userID;

    public function __construct(int $userAnswerID, string $answer)
    {
        $this->userAnswerID = $userAnswerID;
        $this->answer = $answer;
    }

    public static function deleteUserAnswers(int $userID, PDO $db)
    {
        $query = 'DELETE FROM UserAnswers WHERE UserID = ?';
        $stmt = $db->prepare($query);
        $stmt->execute([$userID]);
    }

    /**
     * $answers -> array -- each value has the following keys:
     *      'questionID' -- PK ID of question
     *      'userID'  -- PK ID of user
     *      'userAnswer' -- raw user answer in text form 
     *      'dateAnswered' -- when they answered
     *      'correct' -- whether or not they answered correctly
     */
    public static function saveUserAnswers(array $answers, PDO $db) : bool
    {
        // prepare everything
        // we don't want to add duplicate rows for a question
        // so that generate-quiz has a bit easier of a time.
        // I couldn't figure out an easy/quick way to have multiple rows for an answered question 
        // where some of them had correct = false and one had correct = true. Probably need a GROUP BY and HAVING. 
        // Keeping only one row in the db for this info makes it easier to generate a quiz. :effort:
        // I'm sure there's a better way.
        $query = 'SELECT 1 FROM UserAnswers WHERE QuestionID = ? AND UserID = ?';
        $searchStmt = $db->prepare($query);
        $insertQuery = "INSERT INTO UserAnswers (Answer, DateAnswered, WasCorrect, QuestionID, UserID) VALUES (?, ?, ?, ?, ?)";
        $insertStmnt = $db->prepare($insertQuery);
        $updateQuery = ' UPDATE UserAnswers SET Answer = ?, DateAnswered = ?, WasCorrect = ? WHERE QuestionID = ? AND UserID = ?';
        $updateStmnt = $db->prepare($updateQuery);
        try {
            foreach ($answers as $answer) {
                $searchParams = [
                    $answer["questionID"],
                    $answer["userID"]
                ];
                $searchStmt->execute($searchParams);
                $didFind = count($searchStmt->fetchAll()) >= 1 ? true : false;
                $insertUpdateParams = [
                    $answer["userAnswer"],
                    $answer["dateAnswered"],
                    $answer["correct"],
                    $answer["questionID"],
                    $answer["userID"]
                ];
                if (!$didFind) {
                    $insertStmnt->execute($insertUpdateParams);
                }
                else {
                    $updateStmnt->execute($insertUpdateParams);
                }
            }   
            return true;
        }
        catch (PDOException $e) {
            return false;
        }
    }
}
