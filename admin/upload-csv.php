<?php
    // if "Type" is undefined, check for an invisible bullet char at gist.github.com -> if exists, erase with hex editor (can show up if exported as UTF8 CSV file)
    require_once(dirname(__FILE__)."/init-admin.php");
    if (!$isWebAdmin) {
        header("Location: $basePath/index.php");
        die();
    }

    $title = "Upload Questions";

    $questionsSuccessfullyAdded = 0;
    $questionsFailedToAdd = 0;
    $errors = "";
    if ($isPostRequest) {
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
                $a[trim($key)] = trim($value);
            }
        });
        array_shift($csv); // remove column header (yay http://php.net/manual/en/function.str-getcsv.php)
        // get all the commentary
        $params = [];
        $query = '
            SELECT CommentaryID, Number, Year, TopicName
            FROM Commentaries c JOIN Years y ON c.YearID = y.YearID
            ORDER BY Year, Number';
        $commentaryStmt = $pdo->prepare($query);
        $commentaryStmt->execute($params);
        $commentaries = $commentaryStmt->fetchAll();
        $commentaryMap = [];
        foreach ($commentaries as $commentary) {
            $commentaryNumber = $commentary["Number"];
            $commentaryTopic = $commentary["TopicName"];
            $commentaryMap[$commentaryNumber . $commentaryTopic] = $commentary;
        }

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
            EndVerseID, CommentaryID, CommentaryStartPage, CommentaryEndPage, CreatorID, IsFlagged, IsDeleted) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                    $questionsFailedToAdd++;
                    $errors .= "Unable to add question: " . $row["Question"] . ". Invalid question type.<br>";
                    continue;
                }


                if ($questionType === "bible-qna" || $questionType === "bible-qna-fill") {
                    // find verse id for start
                    $bookName = $row["Start Book"];
                    $chapterNumber = $row["Start Chapter"];
                    $verseNumber = $row["Start Verse"];
                    if (isset($rawBooks[$bookName]) 
                        && isset($rawBooks[$bookName][$chapterNumber]) 
                        && isset($rawBooks[$bookName][$chapterNumber][$verseNumber])) {
                        $startVerseID = $rawBooks[$bookName][$chapterNumber][$verseNumber];
                    }
                    else {
                        $questionsFailedToAdd++;
                        $errors .= "Unable to add Bible question: " . $row["Question"] . ". Invalid book name, chapter, and/or verse.<br>";
                        continue;
                    }
                    $bookName = $row["End Book"];
                    $chapterNumber = $row["End Chapter"];
                    $verseNumber = $row["End Verse"];
                    if ($bookName !== "") {
                        if (isset($rawBooks[$bookName]) 
                            && isset($rawBooks[$bookName][$chapterNumber]) 
                            && isset($rawBooks[$bookName][$chapterNumber][$verseNumber])) {
                            $endVerseID = $rawBooks[$bookName][$chapterNumber][$verseNumber];
                        }
                        else {
                            $endVerseID = NULL;
                        }
                    }
                    else {
                        $endVerseID = NULL;
                    }
                    
                    $commentaryID = NULL;
                    $commentaryStartPage = NULL;
                    $commentaryEndPage = NULL;
                }
                else if ($questionType === "commentary-qna" || $questionType === "commentary-qna-fill") {
                    $commentaryNumber = $row["Commentary Number"];
                    $commentaryTopic = $row["Commentary Topic"];
                    $commentaryStartPage = $row["Start Page"];
                    $commentaryEndPage = $row["End Page"];
                    $commentaryKey = $commentaryNumber . $commentaryTopic;
                    if (isset($commentaryMap[$commentaryKey])) {
                        $commentaryID = $commentaryMap[$commentaryKey]["CommentaryID"];
                    }
                    else {
                        $questionsFailedToAdd++;
                        $errors .= "Unable to add commentary question: " . $row["Question"] . ". Invalid number and/or topic.<br>";
                        continue;
                    }

                    $startVerseID = NULL;
                    $endVerseID = NULL;
                }

                $points = isset($row["Points"]) ? $row["Points"] : "";
                if (trim($points) == "") {
                    $points = "1";
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
                    $points,
                    $_SESSION["UserID"],
                    $startVerseID,
                    $endVerseID,
                    $commentaryID,
                    $commentaryStartPage,
                    $commentaryEndPage,
                    $_SESSION["UserID"],
                    FALSE,
                    FALSE
                ];
                //print_r($params);
                //die();
                $stmt->execute($params);
                $questionsSuccessfullyAdded++;
            }
            catch (PDOException $e) {
                $errors .= "Error inserting question " . $row["Question"] . ": " . $e->getMessage() . "<br>";
                $questionsFailedToAdd++;
                //print_r($e);
                //die();
            }
        }
    }
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href=".">Back</a></p>

<?php if ($isPostRequest) { ?>
    <p>
        <b>Upload results:</b> <?= $questionsSuccessfullyAdded ?> questions successfully added. 
        <?php if ($questionsFailedToAdd > 0) { ?>
            <?= $questionsFailedToAdd ?> questions couldn't be added to the system.</p> <!-- close of initial paragraph -->
            <?php if ($errors !== "") { ?>
                    <p><?= $errors ?></p>
            <?php } ?>
        <?php } else { ?>
            </p> <!-- close of initial paragraph -->
        <?php } ?>
<?php } ?>

<h4>Upload Questions from Excel CSV File</h4>

<p>Directions can be found below the upload form. Please read and follow them carefully, even though they are lengthy. It is worth your time to read through them once in order to avoid aggravation later.</p>

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
        <button class="btn waves-effect waves-light submit blue" type="submit" name="action">Upload Questions</button>
    </form>
</div>

<h4>Upload Form Directions</h4>

<p>Using the upload form requires using Microsoft Excel software. Technically, other methods are possible, but they are not officially supported.</p>
<p>The sample upload file for filling out can be downloaded by clicking the following link: <a class="btn-flat waves-effect waves-light blue white-text" href="<?= $basePath ?>/files/offline-question-sheet.xlsx" target="_blank">Download Sample File</a></p>
<p>Make sure to use the sample upload file whenever you want to upload questions. You may erase everything in the file <em>except</em> the first row of <b>bold</b> headers. If your file does not have the right column headers, things will not work!</p>
<p>Directions:</p>
<ol>
    <li>Before uploading any questions or using the sample upload file, make sure that all of the <a href="<?= $basePath ?>/admin/view-books.php" target="_blank">Bible books</a>, <a href="<?= $basePath ?>/admin/view-books.php" target="_blank">Bible chapters</a> (with proper verse count), and <a href="<?= $basePath ?>/admin/view-commentaries.php" target="_blank">commentary volumes</a> are set up with the values that you will be using in the upload file.</li>
    <li>Download the above linked sample file to your computer. Note the format of the values under each column. Your data will be required to be in the same format. No typos are acceptable. In the samples below, quote marks are used to delineate acceptable values; do not use the quote marks when typing in the file unless they are part of the question's question or answer. For reference, the accepted values for each column are:
        <ul class="browser-default">
            <li><b>Type</b>: "Bible" or "Commentary"</li>
            <li><b>Fill in?</b>: "True" if adding a fill in the blank question or "False" otherwise.</li>
            <li><b>Question</b>: Question text. The maximum length for a question is 10,000 characters. (A character is one letter, such as 'A'.)</li>
            <li><b>Answer</b>: Answer text for the question. Do not use if adding a fill in the blank question. The maximum length for an answer is 10,000 characters. (A character is one letter, such as 'A'.)</li>
            <li><b>Points</b>: Number of points for the question. Should be a number like 32 and not "thirty-two". If left blank, this value defaults to 1.</li>
            <li><b>Start Book</b>: Name of the Bible book for the question's starting verse. Not required if adding a commentary question. Must match a Bible book already in the system.</li>
            <li><b>Start Chapter</b>: Chapter number for the question's starting verse. Should be a number like 32 and not "thirty-two". Must match a chapter already in the system for the given Bible book. Not required if adding a commentary question.</li>
            <li><b>Start Verse</b>: Verse number for the question's starting verse. Should be a number like 32 and not "thirty-two". Must be greater than 0 and less than or equal to the number of verses for the given Bible book and chapter already in the system. Not required if adding a commentary question.</li>
            <li><b>End Book</b>: Name of the Bible book for the question's ending verse. Not required if adding a commentary question or if the start verse is the same as the end verse. Must match a Bible book already in the system.</li>
            <li><b>End Chapter</b>: Chapter number for the question's ending verse. Not required if adding a commentary question or if the start verse is the same as the end verse. Should be a number like 32 and not "thirty-two". Must match a chapter already in the system for the given Bible book.</li>
            <li><b>End Verse</b>: Verse number for the question's ending verse. Not required if adding a commentary question or if the start verse is the same as the end verse. Should be a number like 32 and not "thirty-two". Must be greater than 0 and less than or equal to the number of verses for the given Bible book and chapter already in the system.</li>
            <li><b>Commentary Number</b>: Volume number for the question. Only use on questions of type "Commentary". Should be a number like 32 and not "thirty-two". Must match a commentary number already in the system.</li>
            <li><b>Commentary Topic</b>: Volume number for the question. Only use on questions of type "Commentary". Must match a commentary topic already in the system for the given commentary number.</li>
            <li><b>Start Page</b>: Start page reference for the question. Only use on questions of type "Commentary". Should be a number like 32 and not "thirty-two". Can be any number.</li>
            <li><b>End Page</b>: End page reference for the question. Only use on questions of type "Commentary". Should be a number like 32 and not "thirty-two". Can be any number. Can be left blank.</li>
        </ul>
    </li>
    <li>Type in as many questions into the upload form as you like. Each question should take one row. Remember to avoid deleting the header row!</li>
    <li>When you're ready to upload the file, choose "File" -> "Save As" from the menu. Save the document as a CSV (Comma delimited) CSV file. <b>If you see an option for CSV UTF-8, do not choose this option. Choose the "CSV (Comma delimited)" on Windows or the "Comma Separated Values (.csv)" option on macOS.</b> Save the file in a location you can find because you'll need it in the next step. If Excel warns you that some features of the worksheet may be lost, just say OK.</li>
    <li>Once you've got the file, simply use the form above to upload the questions. Click "Choose CSV File", find the file that you saved in the previous step, and choose that one. Then click "Upload Questions" and wait. The web page will tell you if any questions were unable to be added as well as the number of questions successfully added. Keep in the mind that the upload questions form does not care if you're adding questions for old years, so if you add questions to another year's books/commentaries, those questions will still upload properly!</li>
</ol>


<?php include(dirname(__FILE__)."/../footer.php") ?>