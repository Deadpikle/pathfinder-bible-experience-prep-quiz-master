<?php
    // if "Type" is undefined, check for an invisible bullet char at gist.github.com -> if exists, erase with hex editor (dunno why it's there...)
    require_once(dirname(__FILE__)."/init-admin.php");
    if ($_SESSION["UserType"] !== "WebAdmin") {
        header("Location: $basePath/index.php");
        die();
    }

    $title = "Upload Questions";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $tmpName = $_FILES['csv']['tmp_name'];
        $contents = file_get_contents($tmpName);
        // split file by items
        $rows = explode("\r", $contents);
        // get csv data
        $csv = array_map('str_getcsv', $rows);
        // make it an associate array with csv keys => values
        array_walk($csv, function(&$a) use ($csv) {
            $a = array_combine($csv[0], $a);
            foreach ($a as $key => $value) {
                $a[trim($key)] = $value;
            }
        });
        array_shift($csv); // remove column header (yay http://php.net/manual/en/function.str-getcsv.php)
        // get all the chapter-verse-data

        $bookQuery = '
        SELECT b.Name AS BookName, c.Number AS ChapterNumber, v.VerseID, v.Number AS VerseNumber
        FROM Books b 
            JOIN Chapters c ON b.BookID = c.BookID
            LEFT JOIN Verses v ON c.ChapterID = v.ChapterID
        ORDER BY b.Name, ChapterNumber, VerseNumber';
        $bookData = $pdo->query($bookQuery)->fetchAll();
        // put it in a nice format for easily querying later
        $rawBooks = [];
        foreach ($bookData as $bookRow) {
            $bookName = $bookRow["BookName"];
            $chapterNumber = $bookRow["ChapterNumber"];
            $verseID = $bookRow["VerseID"];
            $verseNumber = $bookRow["VerseNumber"];
            if (!isset($rawBooks[$bookName])) {
                $rawBooks[$bookName] = [];
            }
            if (!isset($rawBooks[$bookName][$chapterNumber])) {
                $rawBooks[$bookName][$chapterNumber] = array();
            }
            $rawBooks[$bookName][$chapterNumber][$verseNumber] = $verseID;
        }
        // prepare the statement
        $query = '
            INSERT INTO Questions (Type, Question, Answer, NumberPoints, LastEditedByID, StartVerseID, 
            EndVerseID, CommentaryVolume, CommentaryStartPage, CommentaryEndPage, CreatorID) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ';
        $stmt = $pdo->prepare($query);
        foreach ($csv as $row) {
            /*$keys = array_keys($row);
            print_r($keys);
            echo "<br><br>";
            print_r($row);
            echo "<br><br>";
            foreach ($keys as $key) {
                echo $key . " => " . $row[$key] . "<br>";
                // if (trim($key) !== $key) {
                //     die("no");
                // }
            }
            var_dump($row);
            echo ($row["Type"]);
            die();*/
            try {
                $questionType = "";
                $isFillInTheBlank = trim($row["Fill in?"]) === "Yes";
                $row["Type"] = trim($row["Type"]);
                if ($row["Type"] === "Bible") {
                    if ($isFillInTheBlank) {
                        $questionType = "bible-qna-fill";
                    }
                    else {
                        $questionType = "bible-qna";
                    }
                }
                else if ($row["Type"] === "Commentary") {
                    if ($isFillInTheBlank) {
                        $questionType = "commentary-qna-fill";
                    }
                    else {
                        $questionType = "commentary-qna";
                    }
                }
                if ($questionType === "") {
                    echo "unable to add question <br>";
                    continue;
                }
                // find verse id for start
                $bookName = $row["Start Book"];
                $chapterNumber = $row["Start Chapter"];
                $verseNumber = $row["Start Verse"];
                if (isset($rawBooks[$bookName]) && isset($rawBooks[$bookName][$chapterNumber]) && isset($rawBooks[$bookName][$chapterNumber][$verseNumber])) {
                    $startVerseID = $rawBooks[$bookName][$chapterNumber][$verseNumber];
                }
                else {
                    $startVerseID = NULL;
                }
                $bookName = $row["End Book"];
                $chapterNumber = $row["End Chapter"];
                $verseNumber = $row["End Verse"];
                if ($bookName !== "") {
                    if (isset($rawBooks[$bookName]) && isset($rawBooks[$bookName][$chapterNumber]) && isset($rawBooks[$bookName][$chapterNumber][$verseNumber])) {
                        $endVerseID = $rawBooks[$bookName][$chapterNumber][$verseNumber];
                    }
                    else {
                        $endVerseID = NULL;
                    }
                }
                else {
                    $endVerseID = NULL;
                }

                $commentaryVolume = $row["Commentary Book"];
                $commentaryStartPage = $row["Start Page"];
                $commentaryEndPage = $row["End Page"];

                if ($questionType == "bible-qna") {
                    $commentaryVolume = NULL;
                    $commentaryStartPage = NULL;
                    $commentaryEndPage = NULL;
                    if ($isFillInTheBlank) {
                        $questionType = "bible-qna-fill";
                    }
                }
                else if ($questionType == "commentary-qna") {
                    $startVerseID = NULL;
                    $endVerseID = NULL;
                    if ($isFillInTheBlank) {
                        $questionType = "commentary-qna-fill";
                    }
                }

                $questionText = trim($row["Question"]);
                $questionText = str_replace('“', '"', $questionText);
                $questionText = str_replace('”', '"', $questionText);
                $questionText = str_replace('‘', "'", $questionText);
                $questionText = str_replace('’', "'", $questionText);
                $answerText = trim($row["Answer"]);
                $answerText = str_replace('“', '"', $answerText);
                $answerText = str_replace('”', '"', $answerText);
                $answerText = str_replace('‘', "'", $answerText);
                $answerText = str_replace('’', "'", $answerText);
                $params = [
                    $questionType, 
                    $questionText,
                    $answerText,
                    $row["Points"],
                    $_SESSION["UserID"],
                    $startVerseID,
                    $endVerseID,
                    $commentaryVolume,
                    $commentaryStartPage,
                    $commentaryEndPage,
                    $_SESSION["UserID"]
                ];
                //print_r($params);
                //die();
                $stmt->execute($params);
            }
            catch (PDOException $e) {
                echo "error inserting question <br>";
                //print_r($e);
                //die();
            }
        }
    }
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href=".">Back</a></p>

<h4>Upload Questions from CSV File</h4>

<p id="directions">WARNING: THIS PAGE IS NOT YET COMPLETE.</p>

<div id="upload">
    <form method="post" enctype="multipart/form-data">
        <div class="file-field input-field">
            <div class="btn">
                <span>Choose CSV File</span>
                <input type="file" id="csv" name="csv" accept=".csv,text/csv">
            </div>
            <div class="file-path-wrapper">
                <input class="file-path validate" type="text">
            </div>
        </div>
        <button class="btn waves-effect waves-light submit" type="submit" name="action">Submit</button>
    </form>
</div>
<?php include(dirname(__FILE__)."/../footer.php") ?>