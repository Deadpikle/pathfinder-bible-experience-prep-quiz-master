<?php
    require_once(dirname(__FILE__)."/init.php");

    $startVerseID = -1;
    $endVerseID = -1;
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
        <div class="row">
            <div class="input-field col s12 m6">
                <textarea id="question-text" class="materialize-textarea"><?= $questionText ?></textarea>
                <label for="question-text">Question</label>
            </div>
            <div class="input-field col s12 m6">
                <textarea id="question-answer" class="materialize-textarea"><?= $answer ?></textarea>
                <label for="question-answer">Answer</label>
            </div>
        </div>
        <div class="row">
            <div class="input-field col s12 m2">
                <input type="number" min="0" id="number-of-points" name="number-of-points" value="<?= $numberOfPoints ?>"/>
                <label for="number-of-points">Number of Points: </label>
            </div>
        </div>
        <div class="row" id="start-verse-div">
            <div class="input-field">
                <input type="hidden" id="start-verse-id" name="start-verse-id" value="-1"/>
                <select class="col s4 m2" id="start-book-select" name="start-book">
                    <option id="book-no-selection-option" value="-1">Select a book...</option>
                </select>
                <select class="col s4 m2" id="start-chapter-select" name="start-chapter">
                    <option id="chapter-no-selection-option" value="-1">Select a chapter...</option>
                </select>
                <select class="col s4 m2" id="start-verse-select" name="start-verse">
                    <option id="verse-no-selection-option" value="-1">Select a verse...</option>
                </select>
            </div>
        </div>
        <div class="row" id="end-verse-div">
            <div class="input-field">
                <input type="hidden" id="end-verse-id" name="end-verse-id" value="-1"/>
                <select class="col s4 m2" id="end-book-select" name="end-book">
                    <option id="book-no-selection-option" value="-1">Select a book...</option>
                </select>
                <select class="col s4 m2" id="end-chapter-select" name="chapter">
                    <option id="chapter-no-selection-option" value="-1">Select a chapter...</option>
                </select>
                <select class="col s4 m2" id="end-verse-select" name="verse">
                    <option id="verse-no-selection-option" value="-1">Select a verse...</option>
                </select>
            </div>
        </div>
        <button class="btn waves-effect waves-light submit" type="submit" name="action">Save</button>
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
            $('#' + prefix + 'chapter-select').material_select();
            $('#' + prefix + 'start-verse-id').val(-1);
            $('#' + prefix + 'last-verse-id').val(-1);
        }

        function setupVerseSelectForChapter(chapter, prefix) {
            $('#' + prefix + 'verse-select option').not(':first').remove();
            var verses = chapter.verses;
            for (var i = 0; i < verses.length; i++) {
                $('#' + prefix + 'verse-select').append("<option value='" + i + "'>" + verses[i].number + "</option>");
            }
            $('#' + prefix + 'verse-select').material_select();
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
                $('#' + prefix + 'book-select').material_select();
                setupChapterSelectForBook(book, prefix);
                $('#' + prefix + 'chapter-select option:eq(' + (j+1) + ')').prop('selected', true);
                $('#' + prefix + 'chapter-select').material_select();
                setupVerseSelectForChapter(chapter, prefix);
                $('#' + prefix + 'verse-select option:eq(' + (k+1) + ')').prop('selected', true);
                $('#' + prefix + 'verse-select').material_select();
                $('#' + prefix + 'verse-id').val(verseID);
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
        $('#start-book-select').material_select();
        $('#end-book-select').material_select();

        if (startVerseID != -1 || endVerseID != -1) {
            var didFindStart = false;
            var didFindEnd = false;
            for (var i = 0; i < books.length; i++) {
                var book = books[i];
                for (var j = 0; j < book.chapters.length; j++) {
                    var chapter = book.chapters[j];
                    for (var k = 0; k < chapter.verses.length; k++) {
                        var verse = chapter.verses[k];
                        if (startVerseID != -1 && verse.verseID == startVerseID) {
                            didFindStart = true;
                            setupInitialValue('start-', i, j, k, book, chapter, startVerseID);
                        }
                        if (endVerseID != -1 && verse.verseID == endVerseID) {
                            didFindEnd = true;
                            setupInitialValue('end-', i, j, k, book, chapter, endVerseID);
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