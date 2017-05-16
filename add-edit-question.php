<?php
    require_once(dirname(__FILE__)."/init.php");

    if ($_GET["type"] == "update") {
        $query = 'SELECT Question, Answer, NumberPoints, IsFlagged, StartVerseID, LastVerseID FROM Questions WHERE QuestionID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET["id"]]);
        $question = $stmt->fetch();
        $questionText = $question["Question"];
        $answer = $question["Answer"];
        $numberOfPoints = $question["NumberPoints"];
        $isFlagged = $question["IsFlagged"];
        $postType = "update";
    }
    else {
        $questionText = "";
        $answer = "";
        $numberOfPoints = "";
        $isFlagged = FALSE;
        $postType = "create";
    }

    $bookQuery = '
    SELECT b.BookID, b.Name, b.NumberChapters,
        c.ChapterID, c.Number AS ChapterNumber, c.NumberVerses,
        v.VerseID, v.Number AS VerseNumber
    FROM Books b 
        JOIN Chapters c ON b.BookID = c.BookID
        LEFT JOIN Verses v ON c.ChapterID = v.ChapterID
    ORDER BY b.Name, ChapterNumber, VerseNumber';
    $bookData = $pdo->query($bookQuery)->fetchAll();

    $lastBookID = -1;
    $lastChapterID = -1;
    $books = array();
    $book = NULL;
    $chapter = NULL;
    foreach ($bookData as $row) {
        if ($row["BookID"] != $lastBookID) {
            $lastBookID = $row["BookID"];
            if ($chapter != NULL) {
                $book["chapters"][] = $chapter;
            }
            if ($book != NULL) {
                $books[] = $book;
            }
            $book = array(
                "name" => $row["Name"], 
                "numberChapters" => $row["NumberChapters"],
                "chapters" => array()
            );
            $chapter = NULL;
        }
        if ($row["ChapterID"] != $lastChapterID) {
            $lastChapterID = $row["ChapterID"];
            if ($chapter != NULL) {
                $book["chapters"][] = $chapter;
            }
            $chapter = array(
                "chapterID" => $row["ChapterID"],
                "number" => $row["ChapterNumber"],
                "numberVerses" => $row["NumberVerses"],
                "verses" => array()
            );
        }
        
        // create verse
        $verse = array(
            "verseID" => $row["VerseID"],
            "number" => $row["VerseNumber"]
        );
        $chapter["verses"][] = $verse;
        // echo(json_encode($chapter));
    }
    // wrap it up
    $book["chapters"][] = $chapter;
    $books[] = $book;

    $bookJSON = json_encode($books);
?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<script type="text/javascript">
    var books = <?= json_encode($books) ?>;
</script>

<p><a href="./index.php">Back</a></p>

<div id="edit-question">
    <form action="ajax/save-question-edits.php?type=<?= $postType ?>" method="post">
        <input type="hidden" name="question-id" value="<?= $GET['id'] ?>"/>
        <p>
            <label for="question-text">Question: </label>
            <textarea name="question-text" value="<?= $questionText ?>"> </textarea>
        </p>
        <p>
            <label for="question-answer">Answer: </label>
            <textarea type="text" name="question-answer" value="<?= $answer ?>"></textarea>
        </p>
        <p>
            <label for="number-of-points">Number of Points: </label>
            <input type="number" min="0" name="number-of-points" value="<?= $numberOfPoints ?>"/>
        </p>
        <p>
            <label for="book">Verse Setup </label>
            <select id="book-select" name="book">
                <option id="book-no-selection-option" value="-1">Select a book...</option>
            </select>
            <select id="chapter-select" name="chapter">
                <option id="chapter-no-selection-option" value="-1">Select a chapter...</option>
            </select>
            <select id="verse-select" name="verse">
                <option id="verse-no-selection-option" value="-1">Select a verse...</option>
            </select>
        </p>
        <p>
            <input type="submit" value="Save"/>
        </p>
    </form>
</div>

<script type="text/javascript">
    // http://stackoverflow.com/a/15965470/3938401
    $(document).ready(function() {
        $('#book-select').change(function() { 
            $('#chapter-select option').not(':first').remove();
            var bookArrayIndex = $(this).val();
            var book = books[bookArrayIndex];
            var chapters = book.chapters;
            for (var i = 0; i < chapters.length; i++) {
                $('#chapter-select').append("<option value='" + i + "'>" + chapters[i].number + "</option>");
            }
        }); 

        $('#chapter-select').change(function() { 
            $('#verse-select option').not(':first').remove();
            var bookArrayIndex = $('#book-select').val();
            var chapterArrayIndex = $(this).val();
            var chapter = books[bookArrayIndex].chapters[chapterArrayIndex];
            var verses = chapter.verses;
            for (var i = 0; i < verses.length; i++) {
                $('#verse-select').append("<option value='" + i + "'>" + verses[i].number + "</option>");
            }
        });


        // setup the book selector
        for (var i = 0; i < books.length; i++) {
            $('#book-select').append("<option value='" + i + "'>" + books[i].name + "</option>");
        }

    }); 
</script>

<?php include(dirname(__FILE__)."/footer.php"); ?>