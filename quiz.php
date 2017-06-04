<?php
    require_once(dirname(__FILE__)."/init.php");

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
</div>

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
    <h5 id="question-text">Question:</h5>
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
    <button id="flag-question" class="btn btn-flat blue white-text waves-effect blue-waves">Flag question</button>
    <button id="next-question" class="btn btn-flat blue white-text waves-effect blue-waves">Next question</button>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        var currentQuestionIndex = 0;
        var questions = [];
        var currentQuestion = null;

        var $correctAnswerText = $("#question-result-correct");
        var $incorrectAnswerText = $("#question-result-wrong");

        var flagQuestion = document.getElementById('flag-question');

        flagQuestion.addEventListener('click', function() {
            // TODO: allow for flagging a question
        }, false);

        function loadQuiz() {
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
                    }
                    else {
                        // no questions! user is done with all questions and should probably reset their saved question answers
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
            if (answer == currentQuestion.answer) {
                $correctAnswerText.show();
                $incorrectAnswerText.hide();
            }
            else {
                $correctAnswerText.hide();
                $incorrectAnswerText.show();
            }
        }

        var checkAnswer = document.getElementById('check-answer');
        checkAnswer.addEventListener('click', function() {
            checkUserAnswer();
            nextQuestion.disabled = false;
        }, false);

        function moveToNextQuestion() {
            currentQuestionIndex++;
            showQuestionAtCurrentIndex();
        }

        var nextQuestion = document.getElementById('next-question');
        nextQuestion.addEventListener('click', function() {
            moveToNextQuestion();
        }, false);

        function showQuestionAtCurrentIndex() {
            $correctAnswerText.hide();
            $incorrectAnswerText.hide();
            nextQuestion.disabled = true;
            currentQuestion = questions[currentQuestionIndex];
            $("#question-text").html(currentQuestion.question);
        }

        loadQuiz();
    });
</script>

<?php include(dirname(__FILE__)."/footer.php") ?>