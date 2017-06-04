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
        <a id="all-questions" class="btn-flat teal white-text">All Questions</a>
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
    <table id="questions" class="striped">
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

        function loadQuestions(loadType) {
            $("#questions").hide();
            $("#loading-bar").show();
            currentlyLoadedType = loadType;
            $.ajax({
                type: "POST",
                url: "ajax/load-questions.php",
                data: {
                    loadType: loadType
                },
                success: function(data) {
                    setupTable(JSON.parse(data));
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    alert(thrownError);
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
            $(element).attr("class", "btn-flat teal white-text");
        }

        function resetQuestionTypeSelectorClasses() {
                $(all).attr("class", "btn-flat waves-effect waves-teal");
                $(recent).attr("class", "btn-flat waves-effect waves-teal");
                $(flagged).attr("class", "btn-flat waves-effect waves-teal");
        }

        var all = document.getElementById('all-questions');
        var recent = document.getElementById('recently-added-questions');
        var flagged = document.getElementById('flagged-questions');

        all.addEventListener('click', function() {
            if (currentlyLoadedType != "all") {
                resetQuestionTypeSelectorClasses();
                setQuestionTypeSelectorSelected(all);
                loadQuestions("all");
            }
        }, false);
        recent.addEventListener('click', function() {
            if (currentlyLoadedType != "recent") {
                resetQuestionTypeSelectorClasses();
                setQuestionTypeSelectorSelected(recent);
                loadQuestions("recent");
            }
        }, false);
        flagged.addEventListener('click', function() {
            if (currentlyLoadedType != "flagged") {
                resetQuestionTypeSelectorClasses();
                setQuestionTypeSelectorSelected(flagged);
                loadQuestions("flagged");
            }
        }, false);

        $("#questions").hide();
        loadQuestions(currentlyLoadedType);
    });
</script>

<?php include(dirname(__FILE__)."/footer.php"); ?>