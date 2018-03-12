<?php
    require_once(dirname(__FILE__)."/../init-admin.php");

    // http://stackoverflow.com/a/31107425/3938401
    // Note: may want to upgrade to https://github.com/ircmaxell/RandomLib at some point
    /**
    * Generate a random string, using a cryptographically secure 
    * pseudorandom number generator (random_int)
    * 
    * For PHP 7, random_int is a PHP core function
    * For PHP 5.x, depends on https://github.com/paragonie/random_compat
    * 
    * @param int $length      How many characters do we want?
    * @param string $keyspace A string of all possible characters
    *                         to select from
    * @return string
    */
    function random_str($length, $keyspace = '023456789abcdefghijklmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ') {
        $str = '';
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }
        return $str;
    }

    function get_entry_code($pdo) {
        $didFindNewCode = FALSE;
        // pre-create the sql statement for faster queries in the db
        $entryCodeQuery = 'SELECT 1 FROM Users WHERE EntryCode = ?';
        $entryCodeStmt = $pdo->prepare($entryCodeQuery);
        $entryCode = "";
        while (!$didFindNewCode) { // this seems dangerous, but given that there are 42 billion possible entry codes, we should be OK ;P
            $entryCode = random_str(6);
            // Make sure entry code doesn't already exist in the db
            $entryCodeStmt->execute([$entryCode]);
            $didFindNewCode = count($entryCodeStmt->fetchAll()) == 1 ? FALSE : TRUE;
        }
        return $entryCode;
    }

    try {
        $clubID = -1;
        if ($isClubAdmin) {
            $clubID = $_SESSION["ClubID"];
        }
        else {
            $clubID = $_POST["club"];
        }
        $userType = -1;
        if ($isClubAdmin) {
            $pathfinderTypeID = 'SELECT UserTypeID FROM UserTypes WHERE Type = ?';
            $pathfinderStmt = $pdo->prepare($pathfinderTypeID);
            $pathfinderStmt->execute(["Pathfinder"]);
            $row = $pathfinderStmt->fetch(); 
            $userTypeID = $row["UserTypeID"];
        }
        else {
            $userTypeID = $_POST["user-type"];
        }

        $params = [
            trim($_POST["username"]),
            $userTypeID,
            $clubID
        ];
        if ($_GET["type"] == "update") {
            $query = '
                UPDATE Users SET Username = ?, UserTypeID = ?, ClubID = ? WHERE UserID = ?
            ';
            $params[] = $_POST["user-id"];
        }
        else if ($_GET["type"] == "create") {
            $entryCode = get_entry_code($pdo);
            $params[] = $entryCode;
            $params[] = $_SESSION["UserID"];
            $query = '
                INSERT INTO Users (Username, UserTypeID, ClubID, EntryCode, CreatedByID) VALUES (?, ?, ?, ?, ?)
            ';
        }
        else {
            die("Invalid type");
        }
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        header("Location: $basePath/admin/view-users.php");
    }
    catch (PDOException $e) {
        print_r($e);
        die();
    }
?>