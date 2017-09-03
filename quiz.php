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
    $fillInPercent = $_POST["fill-in-percent"];
    $shouldAvoidPastCorrect = "false";
    if (isset($_POST["no-questions-answered-correct"]) && $_POST["no-questions-answered-correct"] != NULL) {
        $shouldAvoidPastCorrect = "true";
    }
    if (isset($_POST["quiz-items"])) {
        $quizItems = $_POST["quiz-items"];
    }
    else {
        $quizItems = array();
    }

?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<script type="text/javascript">
    var maxQuestions = <?= $maxQuestions ?>;
    var maxPoints = <?= $maxPoints ?>;
    var questionTypes = "<?= $questionTypes ?>";
    var questionOrder = "<?= $questionOrder ?>";
    var fillInPercent = <?= $fillInPercent ?>;
    var shouldAvoidPastCorrect = <?= $shouldAvoidPastCorrect ?>;
    var quizItems = <?= json_encode($quizItems) ?>;
    var userID = <?= $_SESSION["UserID"] ?>; // is this really wise?
</script>

<div id="quiz-taking">
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

    <div class="hidden" id="take-quiz">
        <h4 id="quiz-progress"></h4>
        <h5 id="question-points"></h5>
        <h6 id="user-points-earned"></h6>
        <div class="divider"></div>
        <div id="qna-question" class="row">
            <h5 id="question-text"></h5>
            <div class="input-field col s12 m6">
                <input type="text" id="quiz-answer" name="quiz-answer" required/>
                <label for="quiz-answer">Answer</label>
            </div>
        </div>
        <div id="fill-in-question">
            <h5 id="fill-in-title"></h5>
            <div class="row">
                <div id="fill-in-data" class="col s10"></div>
            </div>
        </div>
        <div id="show-answer-div" class="row">
            <div class="input-field col s12" id="">
                <button id="show-answer" class="btn btn-flat blue white-text waves-effect blue-waves">Show answer</button>
            </div>
        </div>
        <div id="answer-divider" class="divider"></div>
        <div id="full-fill-in-div" class="input-field col s12 m4">
            <input type="checkbox" id="full-fill-in" name="full-fill-in"/>
            <label class="black-text" for="full-fill-in">View fill in the blank as full text with answers in <b>bold</b></label>
        </div>
        <p id="quiz-question-show-answer">The answer is:</p>
        <div id="points-earned-row" class="row">
            <div class="input-field col s6 m3" id="">
                <input type="checkbox" name="correct-answer" id="correct-answer" value="0"/>
                <label class="black-text" for="correct-answer">Correct Answer</label>
            </div>
            <div class="input-field col s6 m2" id="points-earned-div">
                <input type="number" name="points-earned" id="points-earned" value="0" min="0" max="100"/>
                <label class="black-text" for="points-earned">Points earned</label>
            </div>
            <div class="input-field col s12 m4">
                <button id="next-question" class="btn btn-flat blue white-text waves-effect blue-waves">Next question</button>
                <button id="tally-points" class="btn btn-flat blue white-text waves-effect blue-waves">Tally Points</button>
            </div>
        </div>
        <div class="divider"></div>
        <!-- TODO: use a single p element with a variety of error messages in JS instead :) -->
        <p class="negative-top-margin" id="question-flagged">Question successfully flagged!</p>
        <p class="negative-top-margin" id="saving-data">Saving answers...</p>
        <p class="negative-top-margin" id="data-saved">Answers successfully saved!</p>
        <button id="flag-question" class="btn btn-flat blue white-text waves-effect blue-waves right-margin">Flag question</button>
        <button id="save-data" class="btn btn-flat blue white-text waves-effect blue-waves right-margin">Save answers</button>
        <button id="end-quiz" class="btn btn-flat blue white-text waves-effect blue-waves">End quiz</button>
    </div>
    <p id="no-questions-available">No questions available! Please try selecting some different Bible chapters, commentaries, and/or resetting your saved answers!</p>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        var currentQuestionIndex = 0;
        var totalPointsEarned = 0;
        var totalPointsPossible = 0;

        var questions = [];
        var userAnswers = [];
        var currentQuestion = null;
        var didSaveAnswers = false;

        var $questionAnswerText = $("#quiz-question-show-answer");

        var qnaDiv = document.getElementById('qna-question');
        var fillInDiv = document.getElementById('fill-in-question');

        var noQuestionsError = document.getElementById('no-questions-available');
        var answersSavedLabel = document.getElementById('data-saved');
        var savingDataLabel = document.getElementById('saving-data');
        var pointsEarnedInput = document.getElementById('points-earned');
        var correctAnswerCheckbox = document.getElementById('correct-answer');
        var nextQuestion = document.getElementById('next-question');
        var tallyPoints = document.getElementById('tally-points');

        var answerDivider = document.getElementById('answer-divider');
        var fullFillInDiv = document.getElementById('full-fill-in-div');
        var fullFillInCheckbox = document.getElementById('full-fill-in');

        $(answersSavedLabel).hide();
        $(savingDataLabel).hide();
        $(tallyPoints).hide();
        var saveData = document.getElementById('save-data');
        var endQuiz = document.getElementById('end-quiz');
        $(saveData).hide();
        $(endQuiz).hide();
        $(noQuestionsError).hide();

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

        saveData.addEventListener('click', function() {
            $(savingDataLabel).show();
            $.ajax({
                type: "POST",
                url: "ajax/save-answers.php",
                data: {
                    answers: userAnswers
                },
                success: function(response) {
                    $(savingDataLabel).hide();
                    if (response.status == 200) {
                        // successfully saved
                        saveData.disabled = true;
                        $("#save-data").show();
                    }
                    else {
                        alert("Error saving answers: " + response);
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    $(savingDataLabel).hide();
                    alert("Unable to save answers. Please make sure you are connected to the internet or try again later.");
                }
            });
        }, false);

        endQuiz.addEventListener('click', function() {
            if (!saveData.disabled) {
                var result = confirm("You haven't saved your answers yet! Are you sure you want to leave this quiz?");
                if (result) {
                    window.location.href = "quiz-setup.php";
                }
            }
            else {
                window.location.href = "quiz-setup.php";
            }
        });

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
                    fillInPercent: fillInPercent,
                    shouldAvoidPastCorrect: shouldAvoidPastCorrect,
                    quizItems: quizItems,
                    userID: userID
                },
                success: function(response) {
                    if (typeof response.questions !== "undefined" && response.questions.length > 0) {
                        questions = response.questions;
                        currentQuestionIndex = 0;
                        showQuestionAtCurrentIndex();
                        $("#take-quiz").show();
                    }
                    else {
                        // no questions! user is done with all questions and should probably reset their saved question answers
                        $(flagQuestion).hide();
                        $(nextQuestion).hide();
                        $(noQuestionsError).show();
                        $(endQuiz).show();
                    }
                    $("#loading-quiz").hide();
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    alert("Unable to generate quiz. Please make sure you are connected to the internet or try again later.");
                }
            });
        }

        function showAnswerToUser() {
            showAnswer.disabled = true;
            correctAnswerCheckbox.checked = false;
            pointsEarnedInput.value = 0;
            $("#points-earned-row").show();
            $(answerDivider).show();
            var isFillIn = isFillInQuestion(currentQuestion.type);
            if (isFillIn) {
                $(fullFillInDiv).show();
                var outputAnswer = currentQuestion.question;
                if (fullFillInCheckbox.checked) {
                    outputAnswer = fillInText(currentQuestion.fillInData, true);
                }
                else {
                    outputAnswer = fillInAnswerString(currentQuestion.fillInData);
                }
                $questionAnswerText.html("The answer is: " + outputAnswer);
            }
            else {
                var outputAnswer = isFillIn ? currentQuestion.question : currentQuestion.answer;
                $questionAnswerText.html("The answer is: " + outputAnswer);
            }
            $questionAnswerText.show();
        }

        $(fullFillInCheckbox).change(function() {
            var outputAnswer = '';
            if (this.checked) {
                outputAnswer = fillInText(currentQuestion.fillInData, true);
            }
            else {
                outputAnswer = fillInAnswerString(currentQuestion.fillInData);
            }
            $questionAnswerText.html("The answer is: " + outputAnswer);
        });

        $(correctAnswerCheckbox).change(function() {
            if (this.checked) {
                pointsEarnedInput.value = currentQuestion.points;
            }
            else {
                pointsEarnedInput.value = 0;
            }
        });

        var showAnswer = document.getElementById('show-answer');
        showAnswer.addEventListener('click', function() {
            $("#qna-question :input").attr("disabled", true);
            $("#fill-in-question :input").attr("disabled", true);
            showAnswerToUser();
            $("#show-answer-div").hide();
            nextQuestion.disabled = false;
            if (currentQuestionIndex == questions.length -1) {
                $(flagQuestion).hide();
                $(nextQuestion).hide();
                $(tallyPoints).show();
            }
        }, false);

        function moveToNextQuestion() {
            if (currentQuestionIndex < questions.length - 1) {
                currentQuestionIndex++;
                showQuestionAtCurrentIndex();
            }
        }

        function saveQuestionResponse() {
            var pointsAchieved = Number(pointsEarnedInput.value);
            if (pointsAchieved > currentQuestion.points) {
                pointsAchieved = currentQuestion.points;
            }
            //var didGetAllPossible = pointsAchieved >= currentQuestion.points;
            // save the user's answer data 
            var wasCorrect = 0;
            if (correctAnswerCheckbox.checked) {
                wasCorrect = 1; // TODO: someday, figure out how to set this as true/false in a way that PHP will be happy in the ajax call
            }
            var userAnswer = "";
            if (isFillInQuestion(currentQuestion.type)) {
                var inputValues = $("#fill-in-data :input").map(function() {
                    return $(this).val();
                });
                // https://stackoverflow.com/a/1424720/3938401 -- have to do some extra work to do a .join()
                for (var i = 0; i < inputValues.length; ++i) {
                    userAnswer += inputValues[i];
                    if (i != inputValues.length - 1) {
                        userAnswer += ", ";
                    }
                }
            }
            else {
                userAnswer = $("#quiz-answer").val()
            }
            var answerData = {
                userAnswer: userAnswer,
                dateAnswered: (new Date()).toISOString().replace('T', ' ').replace('Z', ''),
                questionID: currentQuestion.id,
                userID: userID,
                correct: wasCorrect // TODO: should this change to didGetAllPossible?
            };
            userAnswers.push(answerData);
            // update points earned & points possible
            totalPointsEarned += pointsAchieved;
            totalPointsPossible += currentQuestion.points;
            var percent = Math.round((totalPointsEarned / totalPointsPossible) * 100);
            var earnedPointsLabel = totalPointsEarned == 1 ? " point" : " points";
            var possiblePointsLabel = totalPointsPossible == 1 ? " point" : " points";
            $("#user-points-earned").html(totalPointsEarned + earnedPointsLabel + " earned out of " + totalPointsPossible + " total" + possiblePointsLabel + " possible (" + percent + "%)");
        }

        nextQuestion.addEventListener('click', function() {
            saveQuestionResponse();
            moveToNextQuestion();
        }, false);

        tallyPoints.addEventListener('click', function() {
            saveQuestionResponse();
            tallyPoints.disabled = true;
            endQuiz.disabled = false;
            $(endQuiz).show();
            $(saveData).show();
        }, false);

        // https://stackoverflow.com/a/1026087/3938401
        function lowercaseFirstLetter(string) {
            if (string.length == 0) {
                return "";
            }
            return string.charAt(0).toLowerCase() + string.slice(1);
        }
        
        function displayQuestion(data) {
            if (!isFillInQuestion(data.type) && !data.question.endsWith("?")) {
                data.question += "?";
            }
            if (isBibleQuestion(data.type)) {
                var verseText = data.startBook + " " + data.startChapter + ":" + data.startVerse;
                if (data.endBook !== "" && data.startVerse != data.endVerse) {
                    if (data.startChapter == data.endChapter) {
                        verseText += "-" + data.endVerse;
                    }
                    else {
                        var endVerse = data.endChapter + ":" + data.endVerse;
                        verseText += "-" + endVerse;
                    }
                }
                if (isFillInQuestion(data.type)) {
                    $("#fill-in-title").empty().html("Fill in the blanks for " + verseText);
                    $("#fill-in-data").empty().html(createFillInInput("#fill-in-data", data.fillInData));
                    $(qnaDiv).hide();
                    $(fillInDiv).show();
                }
                else {
                    var questionText = "According to " + verseText + ", " + lowercaseFirstLetter(data.question);
                    $("#question-text").html(questionText);
                    $(qnaDiv).show();
                    $(fillInDiv).hide();
                }
            }
            else if (isCommentaryQuestion(data.type)) {
                var pageStr = pageString(data.startPage, data.endPage);
                if (isFillInQuestion(data.type)) {
                    var questionTitleText = "Fill in the blanks for SDA Bible Commentary, Volume " + data.volume + ", "
                        + pageStr;
                    $("#fill-in-title").empty().html(questionTitleText);
                    $("#fill-in-data").empty().html(createFillInInput("#fill-in-data", data.fillInData));
                    $(qnaDiv).hide();
                    $(fillInDiv).show();
                }
                else {
                    var questionText = "According to the SDA Bible Commentary, Volume " + data.volume + ", " 
                        + pageStr + ", " + lowercaseFirstLetter(data.question);
                    $("#question-text").html(questionText);
                    $(qnaDiv).show();
                    $(fillInDiv).hide();
                }
            }

            // show number of points
            var pointsLabel = data.points == 1 ? " point" : " points";
            var numberOfPoints = data.points + pointsLabel + " Possible";
            $("#question-points").html(numberOfPoints);
            // show quiz progress
            var progress = "Question " + data.number + " of " + questions.length + "";
            $("#quiz-progress").html(progress)
        }

        function showQuestionAtCurrentIndex() {
            $questionAnswerText.hide();
            nextQuestion.disabled = true;
            $("#qna-question :input").attr("disabled", false);
            $("#fill-in-question :input").attr("disabled", false);
            $("#question-flagged").hide();
            $("#quiz-answer").val("");
            $("#points-earned-row").hide();
            $("#show-answer-div").show();
            $(answerDivider).hide();
            $(fullFillInDiv).hide();
            showAnswer.disabled = false;
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