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
    $loadOldClubsQuery = '
        SELECT ClubID, Name, URL
        FROM Clubs-Old
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
    $loadOldUsersQuery = '
        SELECT UserID, Username, LastLoginDate, EntryCode, Password, UserTypeID, ClubID, CreatedByID
        FROM Users-Old
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

    $loadOldQuestionsQuery = '
        SELECT QuestionID, Question, Answer, NumberPoints, DateCreated, DateModified, Type, CommentaryVolume, CommentaryStartPage, CommentaryEndPage, CreatorID, LastEditedByID, StartVerseID, EndVerseID
        FROM Questions-Old
    ';
    $insertIntoQuestionsQuery = '
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
?>