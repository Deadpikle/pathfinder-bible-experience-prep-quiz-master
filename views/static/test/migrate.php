<?php
    require_once(dirname(__FILE__)."/../init.php");
    
    echo "Migrating db...";

    $userTypeMap = [
        1 => 2, // pathfinder
        2 => 3, // club admin
        3 => 5, // web admin
        4 => 1  // guest
    ];
    $commentaryMap = [ // volume # to CommentaryID
        4 => 1, // Daniel
        3 => 2  // Esther
    ];
    $clubIDMap = [];
    $userIDMap = [];
    $questionIDMap = [];
    $uccConfID = 2;

    // transfer clubs
    echo "Transferring old clubs...";
    $loadOldClubsQuery = '
        SELECT ClubID, Name, URL
        FROM `Clubs-Old`
    ';
    $insertIntoClubsQuery = '
        INSERT INTO Clubs (Name, URL, ConferenceID) VALUES (?, ?, ?)
    ';
    $loadOldClubsStatement = $pdo->prepare($loadOldClubsQuery);
    $params = [];
    $loadOldClubsStatement->execute($params);
    $oldClubs = $loadOldClubsStatement->fetchAll();
    $insertClubStmnt = $pdo->prepare($insertIntoClubsQuery);
    foreach ($oldClubs as $oldClub) {
        $params = [
            $oldClub["Name"],
            $oldClub["URL"],
            $uccConfID
        ];
        $insertClubStmnt->execute($params);
        $clubIDMap[(int)$oldClub["ClubID"]] = $pdo->lastInsertId();
    }

    // transfer users (and make sure they get to the right clubs)
    echo "Transferring old users...";
    $loadOldUsersQuery = '
        SELECT UserID, Username, LastLoginDate, EntryCode, Password, UserTypeID, ClubID, CreatedByID
        FROM `Users-Old`
    ';
    $insertIntoUsersQuery = '
        INSERT INTO Users (Username, LastLoginDate, EntryCode, Password, UserTypeID, ClubID, CreatedByID) VALUES (?, ?, ?, ?, ?, ?, ?)
    ';
    $params = [];
    $loadOldUsersStmnt = $pdo->prepare($loadOldUsersQuery);
    $loadOldUsersStmnt->execute($params);
    $oldUsers = $loadOldUsersStmnt->fetchAll();
    $insertUserStmnt = $pdo->prepare($insertIntoUsersQuery);
    foreach ($oldUsers as $oldUser) {
        if (isset($userIDMap[$oldUser["CreatedByID"]])) {
            $createdBy = $userIDMap[$oldUser["CreatedByID"]];
        }
        else {
            $createdBy = 2;
        }
        $params = [
            $oldUser["Username"],
            $oldUser["LastLoginDate"],
            $oldUser["EntryCode"],
            "",
            $userTypeMap[(int)$oldUser["UserTypeID"]],
            $clubIDMap[$oldUser["ClubID"]],
            $createdBy
        ];
        $insertUserStmnt->execute($params);
        $userIDMap[(int)$oldUser["UserID"]] = $pdo->lastInsertId();
    }
    
    // transfer questions
    echo "Transferring old questions...";

    $loadOldQuestionsQuery = '
        SELECT QuestionID, Question, Answer, NumberPoints, DateCreated, DateModified, Type, CommentaryVolume, CommentaryStartPage, CommentaryEndPage, CreatorID, LastEditedByID, StartVerseID, EndVerseID
        FROM `Questions-Old`
    ';
    $insertIntoQuestionsQuery = '
        INSERT INTO Questions (Question, Answer, NumberPoints, DateCreated, DateModified, Type, CommentaryStartPage, CommentaryEndPage, IsDeleted, CreatorID, LastEditedByID, StartVerseID, EndVerseID, CommentaryID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ';
    $params = [];
    $loadOldQuestionsStmnt = $pdo->prepare($loadOldQuestionsQuery);
    $loadOldQuestionsStmnt->execute($params);
    $oldQuestions = $loadOldQuestionsStmnt->fetchAll();
    $insertQuestionStmnt = $pdo->prepare($insertIntoQuestionsQuery);
    foreach ($oldQuestions as $oldQuestion) {
        if (isset($userIDMap[$oldQuestion["CreatorID"]])) {
            $createdBy = $userIDMap[$oldQuestion["CreatorID"]];
        }
        else {
            $createdBy = 2;
        }
        if (isset($userIDMap[$oldQuestion["LastEditedByID"]])) {
            $editedBy = $userIDMap[$oldQuestion["LastEditedByID"]];
        }
        else {
            $editedBy = 2;
        }
        if (isset($oldQuestion["CommentaryVolume"]) && $oldQuestion["CommentaryVolume"] != null && $oldQuestion["CommentaryVolume"] != "") {
            $commentaryID = $commentaryMap[(int)$oldQuestion["CommentaryVolume"]];
        }
        else {
            $commentaryID = null;
        }
        $params = [
            $oldQuestion["Question"],
            $oldQuestion["Answer"],
            $oldQuestion["NumberPoints"],
            $oldQuestion["DateCreated"],
            $oldQuestion["DateModified"],
            $oldQuestion["Type"],
            $oldQuestion["CommentaryStartPage"],
            $oldQuestion["CommentaryEndPage"],
            0,
            $createdBy,
            $editedBy,
            $oldQuestion["StartVerseID"],
            $oldQuestion["EndVerseID"],
            $commentaryID
        ];
        $insertQuestionStmnt->execute($params);
        $questionIDMap[(int)$oldQuestion["QuestionID"]] = $pdo->lastInsertId();
    }

    // transfer old user answers
    echo "Transferring old user answers...";
    $loadOldAnswersQuery = '
        SELECT Answer, DateAnswered, WasCorrect, QuestionID, UserID
        FROM `UserAnswers-Old`
    ';
    $insertIntoAnswersQuery = '
        INSERT INTO UserAnswers (Answer, DateAnswered, WasCorrect, QuestionID, UserID) VALUES (?, ?, ?, ?, ?)
    ';
    $params = [];
    $loadOldAnswersStmnt = $pdo->prepare($loadOldAnswersQuery);
    $loadOldAnswersStmnt->execute($params);
    $oldAnswers = $loadOldAnswersStmnt->fetchAll();
    $insertAnswerStmnt = $pdo->prepare($insertIntoAnswersQuery);
    foreach ($oldAnswers as $oldAnswer) {
        if (isset($questionIDMap[$oldAnswer["QuestionID"]])) {
            $questionID = $questionIDMap[$oldAnswer["QuestionID"]];
        }
        else {
            echo "Couldn't find old answer question ID";
            continue;
        }
        if (isset($userIDMap[$oldAnswer["UserID"]])) {
            $userID = $userIDMap[$oldAnswer["UserID"]];
        }
        else {
            echo "Couldn't find old answer user ID";
            continue;
        }
        $params = [
            $oldAnswer["Answer"],
            $oldAnswer["DateAnswered"],
            $oldAnswer["WasCorrect"],
            $questionID,
            $userID
        ];
        $insertAnswerStmnt->execute($params);
    }

    // transfer old user flagged questions
    echo "Transferring old user flagged...";
    $loadOldFlaggedQuery = '
        SELECT UserID, QuestionID
        FROM `UserFlagged-Old`
    ';
    $insertIntoFlaggedQuery = '
        INSERT INTO UserFlagged (UserID, QuestionID) VALUES (?, ?)
    ';
    $params = [];
    $loadOldFlaggedStmnt = $pdo->prepare($loadOldFlaggedQuery);
    $loadOldFlaggedStmnt->execute($params);
    $oldFlagged = $loadOldFlaggedStmnt->fetchAll();
    $insertFlaggedStmnt = $pdo->prepare($insertIntoFlaggedQuery);
    foreach ($oldFlagged as $flagged) {
        if (isset($questionIDMap[$flagged["QuestionID"]])) {
            $questionID = $questionIDMap[$flagged["QuestionID"]];
        }
        else {
            echo "Couldn't find flagged question ID";
            continue;
        }
        if (isset($userIDMap[$flagged["UserID"]])) {
            $userID = $userIDMap[$flagged["UserID"]];
        }
        else {
            echo "Couldn't find flagged user ID";
            continue;
        }
        $params = [
            $userID,
            $questionID
        ];
        $insertFlaggedStmnt->execute($params);
    }
    echo "Done!";
?>