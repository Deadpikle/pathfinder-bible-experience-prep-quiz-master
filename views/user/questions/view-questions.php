
<!-- https://github.com/Dogfalo/materialize/issues/1376 -->
<style type="text/css">
    [type="checkbox"]:not(:checked), [type="checkbox"]:checked {
        position: static;
        left: 0px; 
        opacity: 1; 
    }
</style>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href=".">Back</a></p>

<?php if (!$app->isGuest) { ?>
    <div id="create" class="row">
        <div class="col s12"> 
            <a class="waves-effect waves-light btn" href="<?= $app->yurl('/questions/add') ?>">Add Question</a>
        </div>
    </div>
<?php } ?>

<div id="question-type-choice">
    <a id="bible-qna" class="btn-flat blue white-text">Bible Q&amp;A</a>
    <a id="commentary-qna" class="btn-flat waves-effect waves-blue">Commentary Q&amp;A</a>
</div>

<div id="display-types">
    <a id="all-questions" class="btn-flat blue white-text">All</a>
    <a id="recently-added-questions" class="btn-flat waves-effect waves-blue">Recently Added</a>
    <a id="flagged-questions" class="btn-flat waves-effect waves-blue">Flagged</a>
</div>

<div class="row" id="language-select-container">
    <div class="input-field col s12 m4">
        <select id="language-select">
            <?php foreach ($languages as $language) {
                    $selected = $language->languageID == $userLanguage->languageID ? 'selected' : '';
            ?>
                <option value="<?= $language->languageID ?>" <?= $selected ?>><?= $language->getDisplayName() ?></option>
            <?php } ?>
            <option value="-1">No language filter</option>
        </select>
        <label for="language-select">Filter by language</label>
    </div>
</div>

<div class="row" id="book-select-container">
    <div class="input-field col s12 m4" id="book-select-field">
        <select id="book-select">
            <option value="-1" selected>No book filter</option>
        </select>
        <label id="book-select-label" for="book-select">Book Filter</lable>
    </div>
    <div class="input-field col s12 m4" id="commentary-select-field">
        <select id="volume-select" class="">
            <option value="-1" selected>No commentary filter</option>
        </select>
        <label id="volume-select-label" for="volume-select">Commentary Filter</lable>
    </div>
    <!-- <label>Book Filter</label> -->
    <div class="input-field col s12 m4">
        <select id="chapter-select">
            <option value="-1" selected>No chapter filter</option>
        </select>
        <label id="chapter-select-label" for="chapter-select">Chapter Filter</lable>
    </div>
    <!-- <label>Chapter Filter</label> -->
    <div class="input-field col s12 m4">
    </div>
    <!-- <label>Commentary Filter</label> -->
</div>
<div class="row">
    <p class="left-margin-fix" id="filter-by-search"><b>Search Question Text, Answer, or Chapter:Verse</b></p>
    <div class="">
        <div class="input-field blue-inputs col s12">
            <p class="negative-top-margin">All above filters apply (Bible Q&amp;A vs Commentary Q&amp;A; book/chapter filter, etc.)</p>
        </div>
        <div class="input-field blue-inputs col s12 m4">
            <input id="online-search-input" name="search" type="text" placeholder="Daniel's diet; 2 Kings 2:11; 3:16">
            <label for="search">Search</label>
        </div>
        <div class="input-field blue-inputs col s12 m8">
            <button id="online-search-button" class="btn-flat blue white-text waves-effect" style="vertical-align:middle">Search</button>
            <button id="online-search-clear-button" class="btn-flat blue white-text waves-effect" style="vertical-align:middle">Clear Search</button>
        </div>
    </div>
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
    var books = <?= json_encode($bookData) ?>;
    var volumes = <?= json_encode($volumes) ?>;
    var isAdmin = <?= $app->isAdmin ? 'true' : 'false' ?>;
    var isGuestMode = <?= $app->isGuest ? 'true' : 'false' ?>;
    var questionURLBase = '<?= $app->yurl('/questions/') ?>';
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
        var searchText = '';

        var previousPage = document.getElementById('prev-page');
        var nextPage = document.getElementById('next-page');
        var onlineSearch = document.getElementById('online-search-button');
        var onlineSearchClear = document.getElementById('online-search-clear-button');

        function moveToPage(pageNumber) {
            currentPageNumber = pageNumber;
            loadQuestions(questionFilter);
        }

        function loadQuestions() {
            $("#questions").hide();
            $("#loading-bar").show();
            $.ajax({
                type: "POST",
                url: '<?= $app->yurl('/questions/load') ?>',
                data: {
                    questionType: questionType,
                    questionFilter: questionFilter,
                    pageSize: pageSize,
                    pageOffset: currentPageNumber * pageSize,
                    bookFilter: bookFilter,
                    chapterFilter: chapterFilter,
                    volumeFilter: volumeFilter,
                    searchText: searchText,
                    languageID: $("#language-select").val()
                },
                success: function(response) {
                    if (typeof response == 'undefined') {
                        showLoadError(response);
                    }
                    else {
                        try {
                            var data = JSON.parse(response);
                            setupTable(data.questions);
                            var totalQuestions = data.questions.length != 0 ? data.questions.length : 0;
                            maxPageNumber = totalQuestions != 0 ? Math.ceil(data.totalQuestions / pageSize) - 1 : 0;
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
                        } catch (e) {
                            showLoadError(e);
                        }
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    showLoadError("");
                }
            });
        }

        function showLoadError(error) {
            var $questionsBody = $("#questions-body");
            $questionsBody.empty();
            previousPage.disabled = true;
            nextPage.disabled = true;
            alert("Unable to load questions. Please make sure you are connected to the internet or try again later.");
            if (error) {
                console.log(error);
            }
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
                html += '<th>Language</th>';
            }
            else if (isCommentaryQuestion(questionType)) {
                html += '<th>Question</th>';
                html += '<th>Answer</th>';
                html += '<th class="nowrap">Fill-in?</th>';
                html += '<th>Volume</th>';
                html += '<th>Points</th>';
                html += '<th>Language</th>';
            }
            if (isAdmin && !isGuestMode) {
                html += '<th></th>';
                html += '<th></th>';
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
                var languageName = question.LanguageName;
                if (question.LanguageAltName != '') {
                    languageName += ' (' + question.LanguageAltName + ')';
                }
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
                    html += '<td>' + languageName + '</td>';
                }
                else if (isCommentaryQuestion(question.Type)) {
                    var volume = commentaryVolumeString(question.CommentaryVolume, question.CommentaryStartPage, question.CommentaryEndPage);
                    volume += ' - ' + question.TopicName;
                    html += '<td>' + question.Question + '</td>';
                    html += '<td>' + answer + '</td>';
                    html += '<td>' + checkboxTypeForFillIn + '</td>';
                    html += '<td>' + volume + '</td>';
                    html += '<td>' + question.NumberPoints + '</td>';
                    html += '<td>' + languageName + '</td>';
                }
                if (isAdmin && !isGuestMode) {
                    html += '<td><a class="waves-effect waves-light btn" href="' + questionURLBase + id + '/edit">Edit</a></td>';
                    html += '<td><a class="waves-effect waves-light btn red" href="' + questionURLBase + id + '/delete">Delete</a></td>';
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

        onlineSearch.addEventListener('click', function() {
            searchText = $("#online-search-input").val();
            loadQuestions();
        });

        // https://stackoverflow.com/a/155263/3938401
        document.getElementById("online-search-input")
            .addEventListener("keyup", function(event) {
            event.preventDefault();
            if (event.keyCode === 13) {
                onlineSearch.click();
            }
        });

        onlineSearchClear.addEventListener('click', function() {
            if ($("#online-search-input").val().trim() != '') {
                $("#online-search-input").val('');
                searchText = '';
                loadQuestions();
            }
        });

        $("#questions").hide();
        setupTableHeader("bible-qna");

        // setup selectors

        function resetAllFilters() {
            bookIndex = 0;
            bookFilter = -1;
            chapterFilter = -1;
            chapterIndex = 0;
            volumeFilter = -1;
            $('#book-select-label').hide();
            $('#chapter-select-label').hide();
        }

        function setupBookSelector() {
            resetAllFilters();
            $('#book-select option').not(':first').remove();
            $("#filter-by-text").html("<b>Filter by Book/Chapter</b>");
            for (var i = 0; i < books.length; i++) {
                $('#book-select').append("<option value='" + i + "'>" + books[i].name + "</option>");
            }
            $('#volume-select').material_select("destroy");
            $('#book-select').material_select();
            $('#book-select-label').show();
            $('#volume-select-label').hide();
            $('#book-select-field').show();
            $('#commentary-select-field').hide();
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
            $('#chapter-select-label').show();
            $('#volume-select-label').hide();
            $('#commentary-select-field').hide();
        }

        function setupVolumeSelector() {
            resetAllFilters();
            $('#volume-select option').not(':first').remove();
            $("#filter-by-text").html("<b>Filter by Commentary Volume</b>");
            for (var i = 0; i < volumes.length; i++) {
                $('#volume-select').append("<option value='" + i + "'>" + volumes[i].displayName + "</option>");
            }
            $('#book-select').material_select("destroy");
            $('#chapter-select').material_select("destroy");
            $('#volume-select').material_select();
            $('#book-select-label').hide();
            $('#chapter-select-label').hide();
            $('#book-select-field').hide();
            $('#volume-select-label').show();
            $('#commentary-select-field').show();
        }

        // language selector

        $('#language-select').change(function() { 
            loadQuestions();
        });

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
                $('#chapter-select-label').hide();
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
                volumeFilter = volumes[volumeArrayIndex].commentaryID;
                currentPageNumber = 0;
            }
            else {
                volumeFilter = -1;
            }
            loadQuestions();
        }); 
        setupBookSelector();
        
        $('#language-select').material_select();
        $('#chapter-select-label').hide();
        $('#volume-select-label').hide();

        // load questions
        loadQuestions();
    });
</script>
