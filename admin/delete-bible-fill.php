<?php
    require_once(dirname(__FILE__)."/init-admin.php");
    
    $title = 'Delete Bible Fill In Questions';

    if (!$isWebAdmin) {
        header("Location: $basePath/index.php");
        die();
    }
    // type can be "chapter" (deleting just one chapter's worth of questions) or "all" (deleting all questions)
    $type = $_GET["type"];

    if ($type === "chapter") {
        $chapterID = $_GET["id"];
        $query = '
            SELECT Name, Number
            FROM Books b JOIN Chapters c ON b.BookID = c.BookID
            WHERE ChapterID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$chapterID]);
        $data = $stmt->fetch();
        if ($data == NULL) {
            die("Invalid chapter id");
        }
        $languages = get_languages($pdo);
        $selectedLanguage = null;
        foreach ($languages as $language) {
            if ($language["LanguageID"] == $_GET["languageID"]) {
                $selectedLanguage = $language;
                break;
            }
        }
        if ($selectedLanguage == NULL) {
            die("Invalid language id");
        }
        $warning = "Are you sure you want to delete all of the Bible fill in the blank questions for <b>" . $data['Name'] . " Chapter " . $data["Number"] . "</b> in the " . language_display_name($selectedLanguage) . " language?";
    }
    else if ($type === "all") {
        $chapterID = -1;
        $warning = "Are you sure you want to delete <b>all</b> Bible fill in the blank questions?";
    }
    else {
        // ?? invalid data
        header("Location: $basePath/index.php");
        die();
    }
    
    if ($isPostRequest && $type == $_POST["type"]) {
        $currentYear = get_active_year($pdo)["YearID"];
        // the weird subquery SELECT * was due to a workaround
        // for the error discussed here: https://stackoverflow.com/q/44970574/3938401
        if ($type === "all") {
            $query = 'DELETE FROM Questions
                      WHERE QuestionID IN (
                        SELECT q.QuestionID 
                        FROM (SELECT * FROM Questions) q 
                            JOIN Verses v ON q.StartVerseID = v.VerseID
                            JOIN Chapters c ON c.ChapterID = v.ChapterID
                            JOIN Books b ON b.BookID = c.BookID
                        WHERE q.Type = "bible-qna-fill"
                            AND b.YearID = ?)';
            $stmt = $pdo->prepare($query);
            $stmt->execute([$currentYear]);
            header("Location: view-bible-fill-in.php");
            die();
        }
        else if ($type === "chapter" && $chapterID == $_POST["chapter-id"] && $selectedLanguage["LanguageID"] == $_POST["language-id"]) {
            $query = 'DELETE FROM Questions 
                      WHERE QuestionID IN (
                        SELECT q.QuestionID 
                        FROM (SELECT * FROM Questions) q 
                            JOIN Verses v ON q.StartVerseID = v.VerseID
                            JOIN Chapters c ON c.ChapterID = v.ChapterID
                            JOIN Books b ON b.BookID = c.BookID
                        WHERE c.ChapterID = ? AND q.Type = "bible-qna-fill"
                            AND b.YearID = ? AND q.LanguageID = ?)';
            $stmt = $pdo->prepare($query);
            $stmt->execute([$chapterID, $currentYear, $selectedLanguage["LanguageID"]]);
            header("Location: view-bible-fill-in.php");
            die();
        }
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href="./view-bible-fill-in.php">Back</a></p>

<div id="delete-book">
    <h4><?= $warning ?></h4>
    <form method="post">
        <input type="hidden" name="type" value="<?= $type ?>"/>
        <input type="hidden" name="chapter-id" value="<?= $chapterID ?>"/>
        <?php if (isset($_GET["languageID"])) { ?>
            <input type="hidden" name="language-id" value="<?= $selectedLanguage["LanguageID"] ?>"/>
        <?php } ?>
        <button class="btn waves-effect waves-light submit red white-text" type="submit" name="action">Delete Questions</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>