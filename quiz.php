<?php
    // TODO: upload wrong/right answer status

    require_once(dirname(__FILE__)."/init.php");

    if (!isset($_POST["max-questions"]) || !isset($_POST["max-points"]) || !isset($_POST["question-types"]) || !isset($_POST["order"])) {
        header("Location: quiz-setup.php");
    }    
    $maxQuestions = $_POST["max-questions"];
    $maxPoints = $_POST["max-points"];
    $questionTypes = $_POST["question-types"];
    $questionOrder = $_POST["order"];
    $shouldAvoidPastCorrect = "false";
    if (isset($_POST["no-questions-answered-correct"]) && $_POST["no-questions-answered-correct"] != NULL) {
        $shouldAvoidPastCorrect = "true";
    }
?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<script type="text/javascript">
    var maxQuestions = <?= $maxQuestions ?>;
    var maxPoints = <?= $maxPoints ?>;
    var questionTypes = "<?= $questionTypes ?>";
    var questionOrder = "<?= $questionOrder ?>";
    var shouldAvoidPastCorrect = <?= $shouldAvoidPastCorrect ?>;
    var userID = <?= $_SESSION["UserID"] ?>; // is this really wise?
</script>

<div id="take-quiz">
    <h4>Quiz Me!</h4>
    <div id="loading-quiz">
        <h4 class="center-align">Generating quiz...</h4>
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
    </div>

    <div id="take-quiz">
        <h5 id="question-text">Question text will be here</h5>
        <h6 id="question-points">Question points will be here</h6>
        <h6 id="quiz-progress">Question x/y text will be here</h6>
        <div class="row">
            <div class="input-field col s12 m6">
                <input type="text" id="quiz-answer" name="quiz-answer" required/>
                <label for="quiz-answer">Answer</label>
            </div>
                <div class="input-field col s6 m6">
                    <button id="check-answer" class="btn btn-flat blue white-text waves-effect blue-waves">Check answer</button>
                </div>
        </div>
        <p id="question-result-correct">That's the right answer! Good job!</p>
        <p id="question-result-wrong">Sorry, that's not the correct answer.</p>
        <p id="question-flagged">Question successfully flagged!</p>
        <button id="flag-question" class="btn btn-flat blue white-text waves-effect blue-waves">Flag question</button>
        <button id="next-question" class="btn btn-flat blue white-text waves-effect blue-waves">Next question</button>
        <a id="end-quiz" class="btn btn-flat blue white-text waves-effect blue-waves" href="quiz-setup.php">End quiz</a>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        var currentQuestionIndex = 0;
        var questions = [];
        var currentQuestion = null;

        var $correctAnswerText = $("#question-result-correct");
        var $incorrectAnswerText = $("#question-result-wrong");

        var endQuiz = document.getElementById('end-quiz');
        $(endQuiz).hide();

        var flagQuestion = document.getElementById('flag-question');
        flagQuestion.addEventListener('click', function() {
            $.ajax({
                type: "POST",
                url: "ajax/flag-question.php",
                data: {
                    questionID: currentQuestion.id
                },
                success: function(response) {
                    if (response.status == 200) {
                        // successfully flagged
                        flagQuestion.disabled = true;
                        $("#question-flagged").show();
                    }
                    else {
                        // 
                        alert("Error flagging question: " + response);
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    alert("Unable to flag question. Please make sure you are connected to the internet or try again later.");
                }
            });
        }, false);

        function loadQuiz() {
            $("#take-quiz").hide();
            $("#loading-quiz").show();
            $.ajax({
                type: "POST",
                url: "ajax/generate-quiz.php",
                data: {
                    maxQuestions: maxQuestions,
                    maxPoints: maxPoints,
                    questionTypes: questionTypes,
                    questionOrder: questionOrder,
                    shouldAvoidPastCorrect: shouldAvoidPastCorrect
                },
                success: function(response) {
                    if (response.questions.length > 0) {
                        questions = response.questions;
                        currentQuestionIndex = 0;
                        showQuestionAtCurrentIndex();
                        $("#take-quiz").show();
                    }
                    else {
                        // no questions! user is done with all questions and should probably reset their saved question answers
                        $(flagQuestion).hide();
                        $(nextQuestion).hide();
                        $(endQuiz).show();
                    }
                    $("#loading-quiz").hide();
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    alert("Unable to generate quiz. Please make sure you are connected to the internet or try again later.");
                }
            });
        }

        function checkUserAnswer() {
            var answer = $("#quiz-answer").val();
            checkAnswer.disabled = true;
            if (answer == currentQuestion.answer) {
                $correctAnswerText.show();
                $incorrectAnswerText.hide();
            }
            else {
                $correctAnswerText.hide();
                $incorrectAnswerText.html("Sorry, that's not the correct answer. The correct answer is: " + currentQuestion.answer + "." );
                $incorrectAnswerText.show();
            }
        }

        var checkAnswer = document.getElementById('check-answer');
        checkAnswer.addEventListener('click', function() {
            checkUserAnswer();
            nextQuestion.disabled = false;
            if (currentQuestionIndex == questions.length -1) {
                $(flagQuestion).hide();
                $(nextQuestion).hide();
                $(endQuiz).show();
            }
        }, false);

        function moveToNextQuestion() {
            if (currentQuestionIndex < questions.length - 1) {
                currentQuestionIndex++;
                showQuestionAtCurrentIndex();
            }
        }

        var nextQuestion = document.getElementById('next-question');
        nextQuestion.addEventListener('click', function() {
            moveToNextQuestion();
        }, false);

        // https://stackoverflow.com/a/1026087/3938401
        function lowercaseFirstLetter(string) {
            return string.charAt(0).toLowerCase() + string.slice(1);
        }
        
        function displayQuestion(data) {
            // TODO: the format for the 'according to' will be different for fill in the blank
            if (!data.question.endsWith("?")) {
                data.question += "?";
            }
            if (data.type == 'bible-qna') {
                var verseText = data.startBook + " " + data.startChapter + ":" + data.startVerse;
                if (data.endBook !== "") {
                    if (data.startChapter == data.endChapter) {
                        verseText += "-" + data.endVerse;
                    }
                    else {
                        var endVerse = data.endChapter + ":" + data.endVerse;
                        verseText += "-" + endVerse;
                    }
                }
                var questionText = "According to " + verseText + ", " + lowercaseFirstLetter(data.question);
                $("#question-text").html(questionText);
            }
            else if (data.type == 'commentary-qna') {
                var pageStr = pageString(data.startPage, data.endPage);
                var questionText = "According to the SDA Bible Commentary, Volume " + data.volume + ", " 
                    + pageStr + ", " + lowercaseFirstLetter(data.question);
                $("#question-text").html(questionText);
            }
            // show number of points
            var numberOfPoints = data.points + " Points";
            $("#question-points").html(numberOfPoints);
            // show quiz progress
            var progress = "(Question " + data.number + "/" + questions.length + ")";
            $("#quiz-progress").html(progress)
        }

        function showQuestionAtCurrentIndex() {
            $correctAnswerText.hide();
            $incorrectAnswerText.hide();
            nextQuestion.disabled = true;
            $("#question-flagged").hide();
            $("#quiz-answer").val("");
            checkAnswer.disabled = false;
            currentQuestion = questions[currentQuestionIndex];
            if (currentQuestion.isFlagged == 0 && currentQuestion.isFlagged == "0") {
                flagQuestion.disabled = false;
            }
            else {
                flagQuestion.disabled = true;
            }
            displayQuestion(currentQuestion);
        }

        loadQuiz();
    });
</script>

<?php include(dirname(__FILE__)."/footer.php") ?>