<?php
    require_once(dirname(__FILE__)."/init.php");
    $isAdminJS = $isAdmin ? "true" : "false";

    // Load all book, chapter, and verse information
    $bookQuery = '
    SELECT b.BookID, b.Name, b.NumberChapters,
        c.ChapterID, c.Number AS ChapterNumber
    FROM Books b  JOIN Chapters c ON b.BookID = c.BookID
    ORDER BY b.Name, ChapterNumber';
    $bookData = $pdo->query($bookQuery)->fetchAll();

    $lastBookID = -1;
    $books = array();
    $book = NULL;
    $chapter = NULL;
    foreach ($bookData as $row) {
        if ($row["BookID"] != $lastBookID) {
            $lastBookID = $row["BookID"];
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
        $chapter = array(
            "chapterID" => $row["ChapterID"],
            "number" => $row["ChapterNumber"]
        );
        $book["chapters"][] = $chapter;
    }
    $books[] = $book; // make sure to get the last item
    $volumes = load_volumes($pdo);
?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<script type="text/javascript">
    var books = <?= json_encode($books) ?>;
    var volumes = <?= json_encode($volumes) ?>;
    var isAdmin = <?= $isAdminJS ?>;
</script>

<!-- https://github.com/Dogfalo/materialize/issues/1376 -->
<style type="text/css">
    [type="checkbox"]:not(:checked), [type="checkbox"]:checked {
        position: static;
        left: 0px; 
        opacity: 1; 
    }
</style>

<p><a href=".">Back</a></p>

<div id="create" class="row">
    <div class="col s12"> 
        <a class="waves-effect waves-light btn" href="add-edit-question.php?type=create">Add Question</a>
    </div>
</div>

<div id="question-type-choice">
    <a id="bible-qna" class="btn-flat blue white-text">Bible Q&amp;A</a>
    <a id="commentary-qna" class="btn-flat waves-effect waves-blue">Commentary Q&amp;A</a>
</div>

<div id="display-types">
    <a id="all-questions" class="btn-flat blue white-text">All</a>
    <a id="recently-added-questions" class="btn-flat waves-effect waves-blue">Recently Added</a>
    <a id="flagged-questions" class="btn-flat waves-effect waves-blue">Flagged</a>
</div>

<div class="row">
    <p class="left-margin-fix" id="filter-by-text">Filter by Book/Chapter</p>
    <select id="book-select" class="col s4 m3">
        <option value="-1" selected>No book filter</option>
    </select>
    <select id="chapter-select" class="col s4 m3">
        <option value="-1" selected>No chapter filter</option>
    </select>
    <select id="volume-select" class="col s4 m3">
        <option value="-1" selected>No commentary filter</option>
    </select>
</div>

<div class="divider"></div>

<div id="questions-table">
    <div id="table-controls">
        <button id="prev-page" class="btn-flat blue white-text waves-effect" disabled>Previous Page</button>
        <button id="next-page" class="btn-flat blue white-text waves-effect" disabled>Next Page</button>
    </div>
    <table id="questions" class="striped responsive-table">
        <thead>
            <tr id="table-header-row">
            </tr>
        </thead>
        <tbody id="questions-body">
        </tbody>
    </table>
</div>

<div id="loading-bar" class="preloader-wrapper active">
    <div class="spinner-layer spinner-blue-only">
        <div class="circle-clipper left">
            <div class="circle"></div>
        </div>
        <div class="gap-patch">
            <div class="circle"></div>
        </div>
        <div class="circle-clipper right">
            <div class="circle"></div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {

        var questionType = "bible-qna";
        var questionFilter = "all";
        var pageSize = 25;
        var currentPageNumber = 0;
        var maxPageNumber = 0;
        var bookIndex = 0;
        var chapterIndex = 0;
        var bookFilter = -1;
        var chapterFilter = -1;
        var volumeFilter = -1;

        var previousPage = document.getElementById('prev-page');
        var nextPage = document.getElementById('next-page');

        function moveToPage(pageNumber) {
            currentPageNumber = pageNumber;
            loadQuestions(questionFilter);
        }

        function loadQuestions() {
            $("#questions").hide();
            $("#loading-bar").show();
            $.ajax({
                type: "POST",
                url: "ajax/load-questions.php",
                data: {
                    questionType: questionType,
                    questionFilter: questionFilter,
                    pageSize: pageSize,
                    pageOffset: currentPageNumber * pageSize,
                    bookFilter: bookFilter,
                    chapterFilter: chapterFilter,
                    volumeFilter: volumeFilter
                },
                success: function(response) {
                    setupTable(response.questions);
                    var totalQuestions = response.questions.length != 0 ? response.questions.length : 0;
                    maxPageNumber = totalQuestions != 0 ? Math.ceil(response.totalQuestions / pageSize) - 1 : 0;
                    if (currentPageNumber == 0) {
                        previousPage.disabled = true;
                    }
                    else {
                        previousPage.disabled = false;
                    }
                    if (maxPageNumber == currentPageNumber) {
                        nextPage.disabled = true;
                    }
                    else {
                        nextPage.disabled = false;
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    var $questionsBody = $("#questions-body");
                    $questionsBody.empty();
                    previousPage.disabled = true;
                    nextPage.disabled = true;
                    alert("Unable to load questions. Please make sure you are connected to the internet or try again later.");
                }
            });
        }

        function setupTableHeader(questionType) {
            var $tableHeaderRow = $('#table-header-row');
            $tableHeaderRow.empty();
            var html = '';
            if (isBibleQuestion(questionType)) {
                html += '<th>Question</th>';
                html += '<th>Answer</th>';
                html += '<th class="nowrap">Fill-in?</th>';
                html += '<th>Start</th>';
                html += '<th>End</th>';
                html += '<th>Points</th>';
            }
            else if (isCommentaryQuestion(questionType)) {
                html += '<th>Question</th>';
                html += '<th>Answer</th>';
                html += '<th class="nowrap">Fill-in?</th>';
                html += '<th>Volume</th>';
                html += '<th>Points</th>';
            }
            if (isAdmin) {
                html += '<th>Edit</th>';
                html += '<th>Delete</th>';
            }
            $tableHeaderRow.append(html);
        }

        function emptyQuestionBody() {
            var $questionsBody = $("#questions-body");
            $questionsBody.empty();
        }

        function setupTable(questions) {
            var $questionsBody = $("#questions-body");
            emptyQuestionBody();

            var unchecked = "<span><input type='checkbox' disabled></input></span>";
            var checked = "<span><input type='checkbox' disabled checked></input></span>";

            for (var i = 0; i < questions.length; i++) {
                var question = questions[i];
                var id = question.QuestionID;
                var html = '<tr>';
                var isFillIn = isFillInQuestion(question.Type);
                var checkboxTypeForFillIn = isFillIn ? checked : unchecked;
                var answer = isFillIn ? "[Fill in the blanks]" : question.Answer;
                if (isBibleQuestion(question.Type)) {
                    var startVerse = question.StartBook + " " + question.StartChapter + ":" + question.StartVerse;
                    var endVerse = "";
                    if (typeof question.EndVerse !== 'undefined' && question.EndVerse != null && question.EndVerse != "") {
                        endVerse = question.EndBook + " " + question.EndChapter + ":" + question.EndVerse;
                    }
                    html += '<td>' + question.Question + '</td>';
                    html += '<td>' + answer + '</td>';
                    html += '<td>' + checkboxTypeForFillIn + '</td>';
                    html += '<td>' + startVerse + '</td>';
                    html += '<td>' + endVerse + '</td>';
                    html += '<td>' + question.NumberPoints + '</td>';
                }
                else if (isCommentaryQuestion(question.Type)) {
                    var volume = commentaryVolumeString(question.CommentaryVolume, question.CommentaryStartPage, question.CommentaryEndPage);
                    html += '<td>' + question.Question + '</td>';
                    html += '<td>' + answer + '</td>';
                    html += '<td>' + checkboxTypeForFillIn + '</td>';
                    html += '<td>' + volume + '</td>';
                    html += '<td>' + question.NumberPoints + '</td>';
                }
                if (isAdmin) {
                    html += '<td><a href="add-edit-question.php?type=update&id=' + id + '">Edit</a></td>';
                    html += '<td><a href="delete-question.php?id=' + id + '">Delete</a></td>';
                }
                html += '</tr>';
                $questionsBody.append(html);
            }
            $("#questions").show();
            $("#loading-bar").hide();
        }

        function setQuestionSelectorSelected(element) {
            $(element).attr("class", "btn-flat blue white-text");
        }

        function resetQuestionTypeSelectorClasses() {
            $(bibleQnA).attr("class", "btn-flat waves-effect waves-blue");
            $(commentaryQnA).attr("class", "btn-flat waves-effect waves-blue");
        }

        function resetQuestionFilterSelectorClasses() {
            $(all).attr("class", "btn-flat waves-effect waves-blue");
            $(recent).attr("class", "btn-flat waves-effect waves-blue");
            $(flagged).attr("class", "btn-flat waves-effect waves-blue");
        }

        function questionTypeSelectorClicked(questionTypeSelected, element) {
            if (questionType != questionTypeSelected) {
                questionType = questionTypeSelected;
                if (questionTypeSelected == "bible-qna") {
                    setupBookSelector();
                }
                else {
                    setupVolumeSelector();
                }
                currentPageNumber = 0;
                resetQuestionTypeSelectorClasses();
                setQuestionSelectorSelected(element);
                emptyQuestionBody();
                setupTableHeader(questionTypeSelected);
                loadQuestions();
            }
        }

        function questionFilterSelectorClicked(questionFilterSelected, element) {
            if (questionFilter != questionFilterSelected) {
                questionFilter = questionFilterSelected;
                currentPageNumber = 0;
                resetQuestionFilterSelectorClasses();
                setQuestionSelectorSelected(element);
                emptyQuestionBody();
                loadQuestions();
            }
        }

        var bibleQnA = document.getElementById('bible-qna');
        var commentaryQnA = document.getElementById('commentary-qna');
        bibleQnA.addEventListener('click', function() {
            questionTypeSelectorClicked("bible-qna", bibleQnA);
        }, false);
        commentaryQnA.addEventListener('click', function() {
            questionTypeSelectorClicked("commentary-qna", commentaryQnA);
        }, false);

        var all = document.getElementById('all-questions');
        var recent = document.getElementById('recently-added-questions');
        var flagged = document.getElementById('flagged-questions');

        all.addEventListener('click', function() {
            questionFilterSelectorClicked("all", all);
        }, false);
        
        recent.addEventListener('click', function() {
            questionFilterSelectorClicked("recent", recent);
        }, false);

        flagged.addEventListener('click', function() {
            questionFilterSelectorClicked("flagged", flagged);
        }, false);

        previousPage.addEventListener('click', function() {
            if (currentPageNumber != 0) {
                moveToPage(currentPageNumber - 1);
            }
        }, false);

        nextPage.addEventListener('click', function() {
            if (currentPageNumber != maxPageNumber) {
                moveToPage(currentPageNumber + 1);
            }
        }, false);

        $("#questions").hide();
        setupTableHeader("bible-qna");

        // setup selectors

        function resetAllFilters() {
            bookIndex = 0;
            bookFilter = -1;
            chapterFilter = -1;
            chapterIndex = 0;
            volumeFilter = -1;
        }

        function setupBookSelector() {
            resetAllFilters();
            $('#book-select option').not(':first').remove();
            $("#filter-by-text").html("Filter by Book/Chapter");
            for (var i = 0; i < books.length; i++) {
                $('#book-select').append("<option value='" + i + "'>" + books[i].name + "</option>");
            }
            $('#volume-select').material_select("destroy");
            $('#book-select').material_select();
        }

        function setupChapterSelectForBook(book) {
            $('#chapter-select option').not(':first').remove();
            if (typeof book !== 'undefined') {
                var chapters = book.chapters;
                for (var i = 0; i < chapters.length; i++) {
                    $('#chapter-select').append("<option value='" + i + "'>" + chapters[i].number + "</option>");
                }
            }
            $('#chapter-select').material_select();
        }

        function setupVolumeSelector() {
            resetAllFilters();
            $('#volume-select option').not(':first').remove();
            $("#filter-by-text").html("Filter by Commentary Volume");
            for (var i = 0; i < books.length; i++) {
                $('#volume-select').append("<option value='" + i + "'>" + volumes[i].name + "</option>");
            }
            $('#book-select').material_select("destroy");
            $('#chapter-select').material_select("destroy");
            $('#volume-select').material_select();
        }

        // setup the book selector
        $('#book-select').change(function() { 
            currentPageNumber = 0;
            var bookArrayIndex = $(this).val();
            if (bookArrayIndex != -1 && bookArrayIndex !== "") {
                var book = books[bookArrayIndex];
                bookFilter = book.bookID;
                bookIndex = bookArrayIndex;
                chapterIndex = 0;
                chapterFilter = -1;
                setupChapterSelectForBook(book);
            }
            else {
                // bookArrayIndex is invalid; clear stuff
                bookIndex = 0;
                bookFilter = -1;
                chapterFilter = -1;
                chapterIndex = 0;
                $('#chapter-select').material_select('destroy');
            }
            loadQuestions();
        }); 
        $('#chapter-select').change(function() { 
            var chapterArrayIndex = $(this).val();
            currentPageNumber = 0;
            if (chapterArrayIndex != -1 && chapterArrayIndex !== "") {
                var chapter = books[bookIndex].chapters[chapterArrayIndex];
                chapterFilter = chapter.chapterID;
                chapterIndex = chapterArrayIndex;
            }
            else {
                chapterFilter = -1;
                chapterIndex = 0;
            }
            loadQuestions();
        }); 
        $('#volume-select').change(function() { 
            currentPageNumber = 0;
            var volumeArrayIndex = $(this).val();
            if (volumeArrayIndex != -1 && volumeArrayIndex !== "") {
                volumeFilter = volumes[volumeArrayIndex].id;
                currentPageNumber = 0;
            }
            else {
                volumeFilter = -1;
            }
            loadQuestions();
        }); 
        setupBookSelector();
        
        // load questions
        loadQuestions();
    });
</script>

<?php include(dirname(__FILE__)."/footer.php"); ?>