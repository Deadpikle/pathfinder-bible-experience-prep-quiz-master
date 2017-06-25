<?php
    require_once(dirname(__FILE__)."/init.php");
?>

<?php include(dirname(__FILE__)."/header.php"); ?>

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

<div class="divider"></div>

<div id="questions-table">
    <div id="table-controls">
        <button id="prev-page" class="btn-flat blue white-text waves-effect" disabled>Previous Page</button>
        <button id="next-page" class="btn-flat blue white-text waves-effect" disabled>Next Page</button>
    </div>
    <table id="questions" class="striped responsive-table">
        <thead>
            <tr id="table-header-row">
                <th>Question</th>
                <th>Answer</th>
                <th>Start</th>
                <th>End</th>
                <th>Points</th>
                <th>Edit</th>
                <th>Delete</th>
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
                    pageOffset: currentPageNumber * pageSize
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
            if (questionType == 'bible-qna') {
                html += '<th>Question</th>';
                html += '<th>Answer</th>';
                html += '<th>Start</th>';
                html += '<th>End</th>';
                html += '<th>Points</th>';
            }
            else if (questionType == 'commentary-qna') {
                html += '<th>Question</th>';
                html += '<th>Answer</th>';
                html += '<th>Volume</th>';
                html += '<th>Points</th>';
            }
            html += '<th>Edit</th>';
            html += '<th>Delete</th>';
            $tableHeaderRow.append(html);
        }

        function emptyQuestionBody() {
            var $questionsBody = $("#questions-body");
            $questionsBody.empty();
        }

        function setupTable(questions) {
            var $questionsBody = $("#questions-body");
            emptyQuestionBody();

            for (var i = 0; i < questions.length; i++) {
                var question = questions[i];
                var id = question.QuestionID;
                var html = '<tr>';
                if (question.Type == "bible-qna") {
                    var startVerse = question.StartBook + " " + question.StartChapter + ":" + question.StartVerse;
                    var endVerse = "";
                    if (typeof question.EndVerse !== 'undefined' && question.EndVerse != null && question.EndVerse != "") {
                        endVerse = question.EndBook + " " + question.EndChapter + ":" + question.EndVerse;
                    }
                    html += '<td>' + question.Question + '</td>';
                    html += '<td>' + question.Answer + '</td>';
                    html += '<td>' + startVerse + '</td>';
                    html += '<td>' + endVerse + '</td>';
                    html += '<td>' + question.NumberPoints + '</td>';
                }
                else if (question.Type == "commentary-qna") {
                    var volume = 'Volume ' + question.CommentaryVolume;// + ' Pages'
                    var startPage = question.CommentaryStartPage;
                    var endPage = question.CommentaryEndPage;
                    if (typeof endPage !== 'undefined' && endPage != null && endPage != "" && endPage > startPage) {
                        volume += ', pp. ' + startPage + '-' + endPage;
                    }
                    else {
                        volume += ', p. ' + startPage;
                    }
                    html += '<td>' + question.Question + '</td>';
                    html += '<td>' + question.Answer + '</td>';
                    html += '<td>' + volume + '</td>';
                    html += '<td>' + question.NumberPoints + '</td>';
                }
                html += '<td><a href="add-edit-question.php?type=update&id=' + id + '">Edit</a></td>';
                html += '<td><a href="delete-question.php?id=' + id + '">Delete</a></td>';
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
        loadQuestions();
    });
</script>

<?php include(dirname(__FILE__)."/footer.php"); ?>