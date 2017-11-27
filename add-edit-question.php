<?php

// TODO: Are ALL questions based on a specific verse of the Bible?

    require_once(dirname(__FILE__)."/init.php");

    if ($isGuest) {
        header('Location: index.php');
        die();
    }

    $questionID = isset($_GET['id']) ? $_GET['id'] : "";
    $startVerseID = -1;
    $endVerseID = -1;
    if ($_GET["type"] == "update") {
        if (!$isAdmin) {
            header("Location: " . $basePath . "/view-questions.php");            
            die();
        }
        $query = '
            SELECT Type, q.Question, Answer, NumberPoints, StartVerseID, EndVerseID, IFNULL(uf.UserFlaggedID, 0) AS IsFlagged,
                CommentaryVolume, CommentaryStartPage, CommentaryEndPage
            FROM Questions q LEFT JOIN UserFlagged uf ON q.QuestionID = uf.QuestionID
            WHERE q.QuestionID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET["id"]]);
        $question = $stmt->fetch();
        $questionType = $question["Type"];
        $isFillIn = is_fill_in($questionType);
        $isFillInText = $isFillIn ? "true" : "false"; // because javascript
        $isFillInCheckedText = $isFillIn ? " checked " : "";
        $questionText = $question["Question"];
        $answer = $question["Answer"];
        $numberOfPoints = $question["NumberPoints"];
        $startVerseID = $question["StartVerseID"];
        $endVerseID = $question["EndVerseID"];
        $isFlagged = $question["IsFlagged"] != "0" && $question["IsFlagged"] != 0 ? TRUE : FALSE;
        $commentaryVolume = $question["CommentaryVolume"];
        $commentaryStartPage = $question["CommentaryStartPage"];
        $commentaryEndPage = $question["CommentaryEndPage"];
        $postType = "update";
        $titleString = "Edit";
    }
    else {
        $questionType = "bible-qna";
        $questionText = "";
        $isFillIn = FALSE;
        $isFillInText = "false"; // because javascript
        $isFillInCheckedText = "";
        $answer = "";
        $numberOfPoints = "1";
        $isFlagged = FALSE;
        $commentaryVolume = "";
        $commentaryStartPage = "";
        $commentaryEndPage = "";
        $postType = "create";
        $titleString = "Create";
    }

    if ($startVerseID == NULL) {
        $startVerseID = -1;
    }
    if ($endVerseID == NULL) {
        $endVerseID = -1;
    }
    if ($commentaryVolume == NULL) {
        $commentaryVolume = -1;
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
    var questionType = '<?= $questionType ?>';
    var isFillInInitially = <?= $isFillInText ?>;
    var startVerseID = <?= $startVerseID ?>;
    var endVerseID = <?= $endVerseID ?>;
    var volume = <?= $commentaryVolume ?>;
</script>

<p><a href="./view-questions.php">Back</a></p>

<h4><?= $titleString ?> Question</h4>

<div id="edit-question">
    <form action="ajax/save-question-edits.php?type=<?= $postType ?>" method="post">
        <input type="hidden" name="question-id" value="<?= $questionID ?>"/>
        <p id="question-type-paragraph">Question Type</p>
        <div id="question-type" class="row">
            <div class="input-field col s12">
                <?php $checked = is_bible_qna($questionType) ? "checked" : ""; ?>
                <input type="radio" class="with-gap" name="question-type" id="bible-qna" value="bible-qna" <?= $checked ?>/>
                <label class="black-text" for="bible-qna">Bible</label>
            </div>
            <div class="input-field col s12">
                <?php $checked = is_commentary_qna($questionType) ? "checked" : ""; ?>
                <input type="radio" class="with-gap" name="question-type" id="commentary-qna" value="commentary-qna" <?= $checked ?>/>
                <label class="black-text" for="commentary-qna">SDA Bible Commentary</label>
            </div>
        </div>
        <div id="question-fill-in" class="row">
            <div class="input-field col s12 m4">
                <input type="checkbox" id="question-is-fill-in-blank" name="question-is-fill-in-blank" <?= $isFillInCheckedText ?> />
                <label class="black-text" for="question-is-fill-in-blank">Fill in the blank</label>
            </div>
        </div>
        <div class="row">
            <p class="section-info">When adding a question, you don't need to add the "According to Daniel 3:4" portion at the beginning of the question. This will be added for you when taking a quiz based upon the start/end verses that you choose below.</p>
            <p class="section-info">For fill in the blank questions, type the text into the question field as you would read it in the Bible/SDA Bible Commentary. Blanks will be added for you when taking a quiz. The answer field is not needed for fill in the blank questions.</p>
            <div class="input-field col s12 m6">
                <textarea id="question-text" name="question-text" class="materialize-textarea" required data-length="10000"><?= $questionText ?></textarea>
                <label for="question-text">Question</label>
            </div>
            <div id="question-answer-div" class="input-field col s12 m6">
                <textarea id="question-answer" name="question-answer" class="materialize-textarea" required data-length="3000"><?= $answer ?></textarea>
                <label for="question-answer">Answer</label>
            </div>
        </div>
        <div id="number-of-points-div" class="row negative-top-margin">
            <div class="input-field col s12 m3">
                <input type="number" min="0" id="number-of-points" name="number-of-points" value="<?= $numberOfPoints ?>" required/>
                <label for="number-of-points">Number of Points</label>
            </div>
        </div>
        <div class="row" id="start-verse-div">
            <p class="section-info">Start Reference</p>
            <div class="input-field">
            <!-- TODO: Don't need the extra hidden input. Can just use the select element. -->
                <input type="hidden" id="start-verse-id" name="start-verse-id" value="-1"/>
                <select class="col s4 m3" id="start-book-select" name="start-book" required>
                    <option id="book-no-selection-option" value="">Select a book...</option>
                </select>
                <select class="col s4 m3" id="start-chapter-select" name="start-chapter" required>
                    <option id="chapter-no-selection-option" value="">Select a chapter...</option>
                </select>
                <select class="col s4 m3" id="start-verse-select" name="start-verse" required>
                    <option id="verse-no-selection-option" value="">Select a verse...</option>
                </select>
            </div>
        </div>
        <div class="row" id="end-verse-div">
            <p class="section-info">End Reference (if question covers more than 1 verse)</p>
            <div class="input-field">
                <input type="hidden" id="end-verse-id" name="end-verse-id" value="-1"/>
                <select class="col s4 m3" id="end-book-select" name="end-book">
                    <option id="book-no-selection-option" value="-1">Select a book...</option>
                </select>
                <select class="col s4 m3" id="end-chapter-select" name="chapter">
                    <option id="chapter-no-selection-option" value="-1">Select a chapter...</option>
                </select>
                <select class="col s4 m3" id="end-verse-select" name="verse">
                    <option id="verse-no-selection-option" value="-1">Select a verse...</option>
                </select>
            </div>
        </div>
        <div class="row commentary-inputs">
            <p class="section-info">Commentary Info</p>
            <select class="col s12 m3" id="commentary-volume" name="commentary-volume" required>
                <option id="commentary-no-selection-option" value="">Select a book...</option>
                <option value="4">Daniel</option>
                <option value="3">Esther</option>
            </select>
        </div>
        <div class="row commentary-inputs negative-top-margin">
            <div class="input-field col s12 m3">
                <input type="number" min="0" id="commentary-start" name="commentary-start" value="<?= $commentaryStartPage ?>" required/>
                <label for="commentary-start">Start Page</label>
            </div>
            <div class="input-field col s12 m3">
                <input type="number" min="0" id="commentary-end" name="commentary-end" value="<?= $commentaryEndPage ?>"/>
                <label for="commentary-end">End Page</label>
            </div>
        </div>
        <?php if ($isFlagged) { ?>
            <div class="row" id="unflag-question">
                <div class="input-field col s12">
                    <input type="checkbox" id="remove-question-flag" name="remove-question-flag"/>
                    <label class="black-text" for="remove-question-flag">Delete flag for this question</label>
                </div>
            </div>
        <?php } ?>
        <button class="btn waves-effect waves-light submit" type="submit" name="action">Save</button>
    </form>
</div>

<script type="text/javascript">
    // http://stackoverflow.com/a/15965470/3938401
    var selectedVerse = null;
    $(document).ready(function() {

        var bibleQuestionType = document.getElementById('bible-qna');
        var commentaryType = document.getElementById('commentary-qna');
        var startVerseDiv = document.getElementById('start-verse-div');
        var endVerseDiv = document.getElementById('end-verse-div');
        var startBook = document.getElementById('start-book-select');
        var startChapter = document.getElementById('start-chapter-select');
        var startVerse = document.getElementById('start-verse-select');

        var commentaryVolume = document.getElementById('commentary-volume');
        var commentaryStartPage = document.getElementById('commentary-start');
        var commentaryEndPage = document.getElementById('commentary-end');

        var fillInBlankCheckbox = document.getElementById('question-is-fill-in-blank');
        var answerField = document.getElementById('question-answer');

        function hideCommentaryDiv() {
            $('.commentary-inputs').hide();
            commentaryVolume.required = false;
            commentaryStartPage.required = false;
        }

        function hideBibleDiv() {
            $(startVerseDiv).hide();
            $(endVerseDiv).hide();
            startBook.required = false;
            startChapter.required = false;
            startVerse.required = false;
        }

        if (questionType == 'bible-qna' || questionType == 'bible-qna-fill') {
            hideCommentaryDiv(); // on page load, default is Bible question [bible-qna]
        }
        else {
            hideBibleDiv();
        }

        bibleQuestionType.addEventListener('click', function() {
            // hide commentary data and set fields as not required
            hideCommentaryDiv();
            // show Bible question data and set fields as required
            $(startVerseDiv).show();
            $(endVerseDiv).show();
            startBook.required = true;
            startChapter.required = true;
            startVerse.required = true;
            fixRequiredSelectorCSS();
        }, false);
        commentaryType.addEventListener('click', function() {
            // hide Bible question data and set fields as not required
            hideBibleDiv();
            // show commentary data and set fields as required
            $('.commentary-inputs').show();
            commentaryVolume.required = true;
            commentaryStartPage.required = true;
            fixRequiredSelectorCSS();
        }, false);


        function setupChapterSelectForBook(book, prefix) {
            $('#' + prefix + 'chapter-select option').not(':first').remove();
            $('#' + prefix + 'verse-select option').not(':first').remove();
            if (typeof book !== 'undefined') {
                var chapters = book.chapters;
                for (var i = 0; i < chapters.length; i++) {
                    $('#' + prefix + 'chapter-select').append("<option value='" + i + "'>" + chapters[i].number + "</option>");
                }
            }
            $('#' + prefix + 'chapter-select').material_select();
            if ($('#' + prefix + 'verse-id').val() != -1) {
                $('#' + prefix + 'verse-select').material_select();
            }
            $('#' + prefix + 'verse-id').val(-1);
            fixRequiredSelectorCSS();
        }

        function setupVerseSelectForChapter(chapter, prefix) {
            $('#' + prefix + 'verse-select option').not(':first').remove();
            if (typeof chapter !== 'undefined') {
                var verses = chapter.verses;
                for (var i = 0; i < verses.length; i++) {
                    $('#' + prefix + 'verse-select').append("<option value='" + i + "'>" + verses[i].number + "</option>");
                }
            }
            $('#' + prefix + 'verse-select').material_select();
            $('#' + prefix + 'verse-id').val(-1);
            fixRequiredSelectorCSS();
        }

        function setupBookSelector(prefix) {
            $('#' + prefix + 'book-select').change(function() { 
                var bookArrayIndex = $(this).val();
                if (bookArrayIndex != -1 && bookArrayIndex !== "") {
                    var book = books[bookArrayIndex];
                    setupChapterSelectForBook(book, prefix);
                    $('#' + prefix + 'verse-id').val(-1);
                }
            }); 
        }

        function setupChapterSelector(prefix) {
            $('#' + prefix + 'chapter-select').change(function() { 
                var bookArrayIndex = $('#' + prefix + 'book-select').val();
                var chapterArrayIndex = $(this).val();
                if (chapterArrayIndex != -1 && chapterArrayIndex !== "") {
                    var chapter = books[bookArrayIndex].chapters[chapterArrayIndex];
                    setupVerseSelectForChapter(chapter, prefix);
                    $('#' + prefix + 'verse-id').val(-1);
                }
            });
        }

        function setupVerseSelector(prefix) {
            $('#' + prefix + 'verse-select').change(function() { 
                var bookArrayIndex = $('#' + prefix + 'book-select').val();
                var chapterArrayIndex = $('#' + prefix + 'chapter-select').val();
                var verseArrayIndex = $(this).val();
                if (verseArrayIndex != -1 && verseArrayIndex !== "") {
                    selectedVerse = books[bookArrayIndex].chapters[chapterArrayIndex].verses[verseArrayIndex];
                    $('#' + prefix + 'verse-id').val(selectedVerse.verseID);
                }
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
            fixRequiredSelectorCSS();
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
        fixRequiredSelectorCSS();
        // setup initial selection for commentary volume
        if ((questionType == 'commentary-qna' || questionType == 'commentary-qna-fill') && volume != "" && volume != -1) {
            //document.getElementById('commentary-volume').value = 'Seven';
            $('#commentary-volume option[value="' + volume + '"]').prop('selected', true);
        }
        $('#commentary-volume').material_select();

        // events for fill in the blank checkbox (fillInBlankCheckbox)
        var previousNumPointsValue = "1";
        function modifyAnswerField(isFillInChecked) {
            // readOnly looks/acts quirky with Materialize, so we're just going to use disabled :\
            if (isFillInChecked) {
                answerField.disabled = true;
                answerField.required = false;
                $("#question-answer-div").hide();
                previousNumPointsValue = $("#number-of-points").val();
                $("#number-of-points").val("1");
                $("#number-of-points-div").hide();
            }     
            else {
                answerField.disabled = false;
                answerField.required = true;
                $("#number-of-points").val(previousNumPointsValue);
                $("#question-answer-div").show();
                $("#number-of-points-div").show();
            }
        }
        $(fillInBlankCheckbox).change(function() {
            modifyAnswerField(this.checked);
        });

        modifyAnswerField(isFillInInitially);

    }); 
</script>

<?php include(dirname(__FILE__)."/footer.php"); ?>