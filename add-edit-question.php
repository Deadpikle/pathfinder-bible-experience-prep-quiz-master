<?php
    require_once(dirname(__FILE__)."/init.php");

    $startVerseID = -1;
    if ($_GET["type"] == "update") {
        $query = 'SELECT Question, Answer, NumberPoints, IsFlagged, StartVerseID, EndVerseID FROM Questions WHERE QuestionID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET["id"]]);
        $question = $stmt->fetch();
        $questionText = $question["Question"];
        $answer = $question["Answer"];
        $numberOfPoints = $question["NumberPoints"];
        $isFlagged = $question["IsFlagged"];
        $startVerseID = $question["StartVerseID"];
        $postType = "update";
    }
    else {
        $questionText = "";
        $answer = "";
        $numberOfPoints = "";
        $isFlagged = FALSE;
        $postType = "create";
    }

    // TODO: refactor to a function
    // Load all book, chapter, and verse information
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
                "bookID" => $row["BookID"],
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
       // echo($row["VerseID"]."<br>");
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
    var startVerseID = <?= $startVerseID ?>;
</script>

<p><a href=".">Back</a></p>

<div id="edit-question">
    <form action="ajax/save-question-edits.php?type=<?= $postType ?>" method="post">
        <input type="hidden" name="question-id" value="<?= $_GET['id'] ?>"/>
        <p>
            <label for="question-text">Question: </label>
            <textarea name="question-text"><?= $questionText ?></textarea>
        </p>
        <p>
            <label for="question-answer">Answer: </label>
            <textarea type="text" name="question-answer"><?= $answer ?></textarea>
        </p>
        <p>
            <label for="number-of-points">Number of Points: </label>
            <input type="number" min="0" name="number-of-points" value="<?= $numberOfPoints ?>"/>
        </p>
        <div id="start-verse-div">
            <input type="hidden" id="start-verse-id" name="start-verse-id" value="-1"/>
            <input type="hidden" id="last-verse-id" name="last-verse-id" value="-1"/>
            <label for="book">Book </label>
            <select id="book-select" name="book">
                <option id="book-no-selection-option" value="-1">Select a book...</option>
            </select>
            Chapter 
            <select id="chapter-select" name="chapter">
                <option id="chapter-no-selection-option" value="-1">Select a chapter...</option>
            </select>
            Verse
            <select id="verse-select" name="verse">
                <option id="verse-no-selection-option" value="-1">Select a verse...</option>
            </select>
        </div>
        <p>
            <input type="submit" value="Save"/>
        </p>
    </form>
</div>

<script type="text/javascript">
    // http://stackoverflow.com/a/15965470/3938401
    var selectedVerse = null;
    $(document).ready(function() {

        function setupChapterSelectForBook(book) {
            $('#chapter-select option').not(':first').remove();
            var chapters = book.chapters;
            for (var i = 0; i < chapters.length; i++) {
                $('#chapter-select').append("<option value='" + i + "'>" + chapters[i].number + "</option>");
            }
            $('#start-verse-id').val(-1);
            $('#last-verse-id').val(-1);
        }

        function setupVerseSelectForChapter(chapter) {
            $('#verse-select option').not(':first').remove();
            var verses = chapter.verses;
            for (var i = 0; i < verses.length; i++) {
                $('#verse-select').append("<option value='" + i + "'>" + verses[i].number + "</option>");
            }
            $('#start-verse-id').val(-1);
            $('#last-verse-id').val(-1);
        }

        $('#book-select').change(function() { 
            var bookArrayIndex = $(this).val();
            var book = books[bookArrayIndex];
            setupChapterSelectForBook(book);
        }); 

        $('#chapter-select').change(function() { 
            var bookArrayIndex = $('#book-select').val();
            var chapterArrayIndex = $(this).val();
            var chapter = books[bookArrayIndex].chapters[chapterArrayIndex];
            setupVerseSelectForChapter(chapter);
        });

        $('#verse-select').change(function() { 
            var bookArrayIndex = $('#book-select').val();
            var chapterArrayIndex = $('#chapter-select').val();
            var verseArrayIndex = $(this).val();
            selectedVerse = books[bookArrayIndex].chapters[chapterArrayIndex].verses[verseArrayIndex];
            $('#start-verse-id').val(selectedVerse.verseID);
            $('#last-verse-id').val(selectedVerse.verseID);
        });

        // setup the book selector
        for (var i = 0; i < books.length; i++) {
            $('#book-select').append("<option value='" + i + "'>" + books[i].name + "</option>");
        }

        if (startVerseID != -1) {
            var didFind = false;
            for (var i = 0; i < books.length; i++) {
                var book = books[i];
                for (var j = 0; j < book.chapters.length; j++) {
                    var chapter = book.chapters[j];
                    for (var k = 0; k < chapter.verses.length; k++) {
                        var verse = chapter.verses[k];
                        if (verse.verseID == startVerseID) {
                            didFind = true;
                            // :eq looks by index, so +1 since index 0 is the 'Select a book...' etc.
                            $('#book-select option:eq(' + (i+1) + ')').prop('selected', true);
                            setupChapterSelectForBook(book);
                            $('#chapter-select option:eq(' + (j+1) + ')').prop('selected', true);
                            setupVerseSelectForChapter(chapter);
                            $('#verse-select option:eq(' + (k+1) + ')').prop('selected', true);
                            $('#start-verse-id').val(startVerseID);
                            $('#last-verse-id').val(startVerseID);
                            break;
                        }
                    }
                    if (didFind) {
                        break;
                    }
                }
                if (didFind) {
                    break;
                }
            }
        }

    }); 
</script>

<?php include(dirname(__FILE__)."/footer.php"); ?>