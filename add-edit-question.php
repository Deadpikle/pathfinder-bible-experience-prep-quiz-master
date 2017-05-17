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
        $endVerseID = $question["EndVerseID"];
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
    var endVerseID = <?= $endVerseID ?>;
</script>

<p><a href="./view-questions.php">Back</a></p>

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
            <label> Start verse </label>
            <select id="start-book-select" name="start-book">
                <option id="book-no-selection-option" value="-1">Select a book...</option>
            </select>
            Chapter 
            <select id="start-chapter-select" name="start-chapter">
                <option id="chapter-no-selection-option" value="-1">Select a chapter...</option>
            </select>
            Verse
            <select id="start-verse-select" name="start-verse">
                <option id="verse-no-selection-option" value="-1">Select a verse...</option>
            </select>
        </div>
        <div id="end-verse-div">
            <input type="hidden" id="end-verse-id" name="end-verse-id" value="-1"/>
            <label> End verse </label>
            <select id="end-book-select" name="end-book">
                <option id="book-no-selection-option" value="-1">Select a book...</option>
            </select>
            Chapter 
            <select id="end-chapter-select" name="chapter">
                <option id="chapter-no-selection-option" value="-1">Select a chapter...</option>
            </select>
            Verse
            <select id="end-verse-select" name="verse">
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

        function setupChapterSelectForBook(book, prefix) {
            $('#' + prefix + 'chapter-select option').not(':first').remove();
            $('#' + prefix + 'verse-select option').not(':first').remove();
            var chapters = book.chapters;
            for (var i = 0; i < chapters.length; i++) {
                $('#' + prefix + 'chapter-select').append("<option value='" + i + "'>" + chapters[i].number + "</option>");
            }
            $('#' + prefix + 'start-verse-id').val(-1);
            $('#' + prefix + 'last-verse-id').val(-1);
        }

        function setupVerseSelectForChapter(chapter, prefix) {
            $('#' + prefix + 'verse-select option').not(':first').remove();
            var verses = chapter.verses;
            for (var i = 0; i < verses.length; i++) {
                $('#' + prefix + 'verse-select').append("<option value='" + i + "'>" + verses[i].number + "</option>");
            }
            $('#' + prefix + 'start-verse-id').val(-1);
            $('#' + prefix + 'last-verse-id').val(-1);
        }

        function setupBookSelector(prefix) {
            $('#' + prefix + 'book-select').change(function() { 
                var bookArrayIndex = $(this).val();
                var book = books[bookArrayIndex];
                setupChapterSelectForBook(book, prefix);
            }); 
        }

        function setupChapterSelector(prefix) {
            $('#' + prefix + 'chapter-select').change(function() { 
                var bookArrayIndex = $('#' + prefix + 'book-select').val();
                var chapterArrayIndex = $(this).val();
                var chapter = books[bookArrayIndex].chapters[chapterArrayIndex];
                setupVerseSelectForChapter(chapter, prefix);
            });
        }

        function setupVerseSelector(prefix) {
            $('#' + prefix + 'verse-select').change(function() { 
                var bookArrayIndex = $('#' + prefix + 'book-select').val();
                var chapterArrayIndex = $('#' + prefix + 'chapter-select').val();
                var verseArrayIndex = $(this).val();
                selectedVerse = books[bookArrayIndex].chapters[chapterArrayIndex].verses[verseArrayIndex];
                $('#' + prefix + 'verse-id').val(selectedVerse.verseID);
            });
        }

        function setupInitialValue(prefix, i, j, k, book, chapter, verseID) {
                // :eq looks by index, so +1 since index 0 is the 'Select a book...' etc.
                $('#' + prefix + 'book-select option:eq(' + (i+1) + ')').prop('selected', true);
                setupChapterSelectForBook(book, prefix);
                $('#' + prefix + 'chapter-select option:eq(' + (j+1) + ')').prop('selected', true);
                setupVerseSelectForChapter(chapter, prefix);
                $('#' + prefix + 'verse-select option:eq(' + (k+1) + ')').prop('selected', true);
                $('#' + prefix + 'verse-id').val(startVerseID);
                $('#' + prefix + 'verse-id').val(startVerseID);
        }

        setupBookSelector('start-');
        setupBookSelector('end-');

        setupChapterSelector('start-');
        setupChapterSelector('end-');

        setupVerseSelector('start-');
        setupVerseSelector('end-');

        // setup the book selector
        for (var i = 0; i < books.length; i++) {
            $('#start-book-select').append("<option value='" + i + "'>" + books[i].name + "</option>");
            $('#end-book-select').append("<option value='" + i + "'>" + books[i].name + "</option>");
        }

        if (startVerseID != -1) {
            var didFindStart = false;
            var didFindEnd = false;
            for (var i = 0; i < books.length; i++) {
                var book = books[i];
                for (var j = 0; j < book.chapters.length; j++) {
                    var chapter = book.chapters[j];
                    for (var k = 0; k < chapter.verses.length; k++) {
                        var verse = chapter.verses[k];
                        if (verse.verseID == startVerseID) {
                            didFindStart = true;
                            setupInitialValue('start-', i, j, k, book, chapter, startVerseID);
                        }
                        if (verse.verseID == endVerseID) {
                            didFindEnd = true;
                            setupInitialValue('end-', i, j, k, book, chapter, startVerseID);
                        }
                        if (didFindStart && didFindEnd) {
                            break;
                        }
                    }
                    if (didFindStart && didFindEnd) {
                        break;
                    }
                }
                if (didFindStart && didFindEnd) {
                    break;
                }
            }
        }

    }); 
</script>

<?php include(dirname(__FILE__)."/footer.php"); ?>