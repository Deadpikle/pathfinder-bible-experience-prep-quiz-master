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

<div id="display-types" class="row">
    <div class="col s12"> 
        <a id="all-questions" class="btn-flat blue white-text">All Questions</a>
        <a id="recently-added-questions" class="btn-flat waves-effect waves-teal">Recently Added</a>
        <a id="flagged-questions" class="btn-flat waves-effect waves-teal">Flagged</a>
    </div>
</div>

<div id="loading-bar" class="preloader-wrapper active">
    <div class="spinner-layer spinner-teal-only">
      <div class="circle-clipper left">
        <div class="circle"></div>
      </div><div class="gap-patch">
        <div class="circle"></div>
      </div><div class="circle-clipper right">
        <div class="circle"></div>
      </div>
    </div>
</div>

<div id="questions-table">
    <div id="table-controls">
        <button id="prev-page" class="btn-flat blue white-text waves-effect">Previous Page</button>
        <button id="next-page" class="btn-flat blue white-text waves-effect">Next Page</button>
    </div>
    <table id="questions" class="striped responsive-table">
        <thead>
            <tr>
                <th>Question</th>
                <th>Answer</th>
                <th>Start Reference</th>
                <th>End Reference</th>
                <th>Number of Points</th>
                <th>Edit</th>
                <th>Delete</th>
            </tr>
        </thead>
        <tbody id="questions-body">
        </tbody>
    </table>
</div>

<script type="text/javascript">
    $(document).ready(function() {

        var currentlyLoadedType = "all";
        var pageSize = 2;
        var currentPageNumber = 0;
        var maxPageNumber = 0;

        var previousPage = document.getElementById('prev-page');
        var nextPage = document.getElementById('next-page');

        function moveToPage(pageNumber) {
            currentPageNumber = pageNumber;
            loadQuestions(currentlyLoadedType);
        }

        function loadQuestions(loadType) {
            $("#questions").hide();
            $("#loading-bar").show();
            currentlyLoadedType = loadType;
            $.ajax({
                type: "POST",
                url: "ajax/load-questions.php",
                data: {
                    loadType: loadType,
                    pageSize: pageSize,
                    pageOffset: currentPageNumber * pageSize
                },
                success: function(data) {
                    var questionData = JSON.parse(data);
                    setupTable(questionData.questions);
                    maxPageNumber = Math.ceil(questionData.totalQuestions / pageSize) - 1;
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
                    alert("Unable to load questions. Please make sure you are connected to the internet or try again later.");
                }
            });
        }

        function setupTable(questions) {
            var $questionsBody = $("#questions-body");
            $questionsBody.empty();

            for (var i = 0; i < questions.length; i++) {
                var question = questions[i];
                var id = question.QuestionID;
                var startVerse = question.StartBook + " " + question.StartChapter + ":" + question.StartVerse;
                var endVerse = "";
                if (typeof question.EndVerse !== 'undefined' && question.EndVerse != null && question.EndVerse != "") {
                    endVerse = question.EndBook + " " + question.EndChapter + ":" + question.EndVerse;
                }
                var html = '<tr>';
                    html += '<td>' + question.Question + '</td>';
                    html += '<td>' + question.Answer + '</td>';
                    html += '<td>' + startVerse + '</td>';
                    html += '<td>' + endVerse + '</td>';
                    html += '<td>' + question.NumberPoints + '</td>';
                    html += '<td><a href="add-edit-question.php?type=update&id=' + id + '">Edit</a></td>';
                    html += '<td><a href="delete-question.php?id=' + id + '">Delete</a></td>';
                html += '</tr>';
                $questionsBody.append(html);
            }
            $("#questions").show();
            $("#loading-bar").hide();
        }

        function setQuestionTypeSelectorSelected(element) {
            $(element).attr("class", "btn-flat blue white-text");
        }

        function resetQuestionTypeSelectorClasses() {
                $(all).attr("class", "btn-flat waves-effect waves-teal");
                $(recent).attr("class", "btn-flat waves-effect waves-teal");
                $(flagged).attr("class", "btn-flat waves-effect waves-teal");
        }

        function questionTypeSelectorClicked(loadType, element) {
            if (currentlyLoadedType != loadType) {
                resetQuestionTypeSelectorClasses();
                setQuestionTypeSelectorSelected(element);
                loadQuestions(loadType);
            }
        }

        var all = document.getElementById('all-questions');
        var recent = document.getElementById('recently-added-questions');
        var flagged = document.getElementById('flagged-questions');

        all.addEventListener('click', function() {
            questionTypeSelectorClicked("all", all);
        }, false);
        
        recent.addEventListener('click', function() {
            questionTypeSelectorClicked("recent", recent);
        }, false);

        flagged.addEventListener('click', function() {
            questionTypeSelectorClicked("flagged", flagged);
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
        loadQuestions(currentlyLoadedType);
    });
</script>

<?php include(dirname(__FILE__)."/footer.php"); ?>