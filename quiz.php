<?php
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
        $quizItems = [];
    }

    if (isset($_POST["enable-question-distribution"]) && $_POST["enable-question-distribution"] != NULL) {
        $enableQuestionDistribution = "true";
    }
    else {
        $enableQuestionDistribution = "true";
    }

    // figure out weights for Bible, commentaries
    $bibleWeights = [];
    $commentaryWeights = [];
    foreach ($_POST as $key => $value) {
        if (str_contains("table-input-chapter-", $key)) {
            $bibleWeights[$key] = $value;
        }
        else if (str_contains("table-input-commentary-", $key)) {
            $commentaryWeights[$key] = $value;
        }
    }

    $languageID = $_POST["language-select"];
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
    var enableQuestionDistribution = <?= $enableQuestionDistribution ?>;
    var bibleWeights = <?= json_encode($bibleWeights) ?>;
    var commentaryWeights = <?= json_encode($commentaryWeights) ?>;
    var languageID = <?= $languageID ?>;
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
        <div class="row" id="quiz-tabs">
            <div class="col s12">
                <ul class="tabs">
                    <li class="tab"><a class="active teal-text" href="#current-question">Current</a></li>
                    <li class="tab"><a class="teal-text" href="#history">Review</a></li>
                    <li class="tab"><a class="teal-text" href="#stats">Statistics</a></li>
                    <div class="indicator teal" style="z-index:1"></div>
                </ul>
            </div>
            <div class="col s12" id="current-question">
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
                <div id="quiz-answer" class="row">
                    <div class="col s12">
                        <p id="quiz-question-show-answer">The answer is:</p>
                    </div>
                </div>
                <div class="row">
                    <div id="full-fill-in-div" class="input-field col s12">
                        <input type="checkbox" id="full-fill-in" name="full-fill-in"/>
                        <label class="black-text" for="full-fill-in">View fill in the blank as full text with answers in <b>bold</b></label>
                    </div>
                </div>
                <div id="points-earned-row" class="row">
                    <div id="correct-answer-div" class="input-field col s6 m3" id="">
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
                <p id="quiz-message"></p>
                <button id="flag-question" class="btn btn-flat blue white-text waves-effect blue-waves right-margin">Flag question</button>
                <button id="save-data" class="btn btn-flat blue white-text waves-effect blue-waves right-margin">Save answers</button>
                <button id="end-quiz" class="btn btn-flat blue white-text waves-effect blue-waves">End quiz</button>
            </div>
        </div>
        <div class="col s12" id="history">
            <p class="no-questions-answered-yet"><em>No questions have been answered yet.</em></p>
            <div id="history-fill-in">
                <input type="checkbox" name="history-fill-in-bold" id="history-fill-in-bold" value="0"/>
                <label class="black-text" for="history-fill-in-bold">View fill in the blank as full text with answers in <b>bold</b></label>
            </div>
            <ol id="history-list">
            </ol>
        </div>
        <div class="col s12" id="stats">
            <p class="no-questions-answered-yet"><em>No questions have been answered yet.</em></p>
            <div id="stats-progress">
                <h5>Overall Statistics</h5>
                <p id="stats-quiz-progress"></p>
                <h5>Overall Bible Statistics</h5>
                <p id="bible-stats-quiz-progress"></p>
                <h5>Overall SDA Bible Commentary Statistics</h5>
                <p id="commentary-stats-quiz-progress"></p>
                <h5>Individual Bible Statistics</h5>
                <ul class="browser-default" id="individual-bible-stats"></ul>
                <h5>Individual SDA Bible Commentary Statistics</h5>
                <ul class="browser-default" id="individual-commentary-stats"></ul>
            </div>
        </div>
    </div>
    <p class="hidden" id="quiz-error"></p>
</div>

<script type="text/javascript">
    // TODO: refactor this to its own file
    function Stats() {
        this.earned = 0;
        this.possible = 0;
        this.percent = 0; // whole number, not decimal
    }
    Stats.prototype.add = function(earnedPoints, possiblePoints) {
        this.earned += earnedPoints;
        this.possible += possiblePoints;
        if (this.possible == 0) {
            this.percent = 0;
        }
        else {
            this.percent = Math.round((this.earned / this.possible) * 100);
        }
        return this;
    }
    Stats.prototype.toString = function() {
        var earnedStr = this.earned == 1 ? "point" : "points";
        var possibleStr = this.possible == 1 ? "point" : "points";
        return this.earned + " " + earnedStr + " earned out of " + this.possible + " total " + possibleStr + " possible (" + this.percent + "%)";
    }
</script>

<script type="text/javascript">
    $(document).ready(function() {
        var currentQuestionIndex = 0;
        var overallStats = new Stats();
        
        // chapter stats: key = Book.Chapter OR CommentaryVolume.Topic
        //                value = { stats: Stats object, isBible, bookName, chapter, volume, topic }  
        var chapterStats = {}; 

        var questions = [];
        var userAnswers = [];
        var currentQuestion = null;
        var didSaveAnswers = false;
        var history = [];

        var answerIsPrefix = "The answer is: ";

        var $questionAnswerText = $("#quiz-question-show-answer");

        var qnaDiv = document.getElementById('qna-question');
        var fillInDiv = document.getElementById('fill-in-question');

        var quizMessageLabel = document.getElementById('quiz-message');
        var pointsEarnedInput = document.getElementById('points-earned');
        var correctAnswerCheckbox = document.getElementById('correct-answer');
        var nextQuestion = document.getElementById('next-question');
        var tallyPoints = document.getElementById('tally-points');

        var answerDivider = document.getElementById('answer-divider');
        var fullFillInDiv = document.getElementById('full-fill-in-div');
        var fullFillInCheckbox = document.getElementById('full-fill-in');

        var historyFillInDiv = document.getElementById('history-fill-in');
        var historyFillInCheckbox = document.getElementById('history-fill-in-bold');

        $(quizMessageLabel).hide();
        $("#stats-progress").hide();
        $(tallyPoints).hide();
        var saveData = document.getElementById('save-data');
        var endQuiz = document.getElementById('end-quiz');
        $(saveData).hide();
        $(endQuiz).hide();
        $(historyFillInDiv).hide();

        function showQuizError(message) {
            $("#quiz-error").html(message).show();
        }

        function showQuizMessage(message) {
            $(quizMessageLabel).html(message).show();
        }

        function hideQuizMessage() {
            $(quizMessageLabel).hide();
        }

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
                        showQuizMessage("Question successfully flagged!");
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
            showQuizMessage("Saving answers...");
            $.ajax({
                type: "POST",
                url: "ajax/save-answers.php",
                data: {
                    answers: userAnswers
                },
                success: function(response) {
                    if (response.status == 200) {
                        // successfully saved
                        saveData.disabled = true;
                        showQuizMessage("Answers successfully saved!");
                    }
                    else {
                        showQuizMessage("Error saving answers: " + response);
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    showQuizMessage("Unable to save answers. Please make sure you are connected to the internet or try again later.");
                }
            });
        }, false);

        endQuiz.addEventListener('click', function() {
            if (!saveData.disabled && questions.length > 0) {
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
                    userID: userID,
                    enableQuestionDistribution: enableQuestionDistribution,
                    bibleWeights: bibleWeights,
                    commentaryWeights: commentaryWeights,
                    languageID: languageID
                },
                success: function(response) {
                    if (typeof response.questions !== "undefined" && response.questions.length > 0) {
                        questions = response.questions;
                        currentQuestionIndex = 0;
                        showQuestionAtCurrentIndex();
                        $("#take-quiz").show();
                        // make sure the first tab is visibly selected. because it's hidden at first,
                        // materialize doesn't draw the little indicator line.
                        $('ul.tabs').tabs('select_tab', 'current-question');
                    }
                    else {
                        // no questions! user is done with all questions and should probably reset their saved question answers
                        $(flagQuestion).hide();
                        $(nextQuestion).hide();
                        showQuizError("No questions available! Please try selecting some different Bible chapters, commentaries, and/or resetting your saved answers!");
                        $(endQuiz).show();
                    }
                    $("#loading-quiz").hide();
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    showQuizError("Unable to generate quiz. Please make sure you are connected to the internet or try again later.");
                    $("#loading-quiz").hide();
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
                $questionAnswerText.html(answerIsPrefix + outputAnswer);
            }
            else {
                $questionAnswerText.html(answerIsPrefix + currentQuestion.answer);
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
            $questionAnswerText.html(answerIsPrefix + outputAnswer);
        });

        $(historyFillInCheckbox).change(function() {
            for (var i = 0; i < history.length; i++) {
                var isFillIn = isFillInQuestion(history[i].type);
                if (isFillIn) {
                    var answerText = "";
                    if (this.checked) {
                        answerText = fillInText(history[i].fillInData, true);
                    }
                    else {
                        answerText = fillInAnswerString(history[i].fillInData);
                    }
                    $("#history-" + i + ' .history-answer').html('<b>Answer:</b> ' + answerText);
                }
            }
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

        function statsKeyForQuestion(question) {
            if (isBibleQuestion(question.type)) {
                return removeSpaces(question.startBook) + "." + question.startChapter;
            }
            else {
                return question.volume + "." + removeSpaces(question.topic);
            }
        }

        function saveQuestionResponse() {
            if (pointsEarnedInput.value === "") {
                pointsEarnedInput.value = 0;
                Materialize.updateTextFields(); // fixes issue where label covers the input amount
            }
            var pointsAchieved = Number(pointsEarnedInput.value);
            if (pointsAchieved > currentQuestion.points) {
                pointsAchieved = currentQuestion.points;
            }
            Math.floor(pointsAchieved);
            // var didGetAllPossible = pointsAchieved >= currentQuestion.points;
            // save the user's answer data 
            var wasCorrect = 0;
            if (correctAnswerCheckbox.checked) {
                wasCorrect = 1; // TODO: someday, figure out how to set this as true/false in a way that PHP will be happy in the ajax call
            }
            var userAnswer = "";
            var isFillIn = isFillInQuestion(currentQuestion.type);
            if (isFillIn) {
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
            overallStats.add(pointsAchieved, currentQuestion.points)
            $("#user-points-earned").html(overallStats.toString());
            // Now update individual stats
            var key = statsKeyForQuestion(currentQuestion);
            // https://stackoverflow.com/a/1098955/3938401 -- checking for key in object
            if (!(key in chapterStats)) {
                chapterStats[key] = {
                    stats: new Stats(),
                    isBible: isBibleQuestion(currentQuestion.type),
                    bookName: currentQuestion.startBook,
                    chapter: currentQuestion.startChapter,
                    volume: currentQuestion.volume,
                    topic: currentQuestion.topic
                };
            }
            var item = chapterStats[key];
            item.stats.add(pointsAchieved, currentQuestion.points);
            updateStatsTab(item);
            // add to user's question history
            var answerText = $questionAnswerText.html();
            answerText = answerText.replace(answerIsPrefix, '');
            var questionText = "";
            if (isFillIn) {
                questionText = fillInText(currentQuestion.fillInData, false, true);
                if (historyFillInCheckbox.checked) {
                    answerText = fillInText(currentQuestion.fillInData, true);
                }
                else {
                    answerText = fillInAnswerString(currentQuestion.fillInData);
                }
            }
            else {
                questionText = $("#question-text").html();
            } 
            addToHistory(currentQuestion.type, questionText, answerText, 
                         userAnswer, correctAnswerCheckbox.checked, pointsAchieved,
                         currentQuestion.fillInData);
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
            hideQuizMessage();
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

        function addToHistory(questionType, questionText, answer, userAnswer, markedCorrect, pointsGained, fillInData) {
            var html = "<li><ul id='history-" + history.length + "'>";
            html += "<li>" + questionText + "</li>";
            html += "<li class='history-answer'><b>Answer:</b> " + answer + "</li>";
            html += "<li><b>Your answer:</b> " + userAnswer + "</li>";
            var markedCorrectStr = markedCorrect ? "Yes" : "No";
            html += "<li><b>Marked correct?</b> " + markedCorrectStr + "</li>";
            html += "<li><b>Points gained:</b> " + pointsGained + "</li>";
            html += "</ul></li>";
            $(historyFillInDiv).show();
            $(".no-questions-answered-yet").hide();
            $("#history-list").append($(html));
            // this could be improved by not storing redundant info in the history array
            // and referencing the original question object,
            // but I also like the idea of having a separate object if I need it, so...
            history.push({
                type: questionType,
                question: questionText,
                answer: answer,
                userAnswer: userAnswer,
                markedCorrect: markedCorrect,
                pointsGained: pointsGained,
                fillInData: fillInData
            });
        }

        function compareBooks(a, b) {
            if (a.bookName == b.bookName) {
                return parseInt(a.chapter, 10) - parseInt(b.chapter, 10);
            }
            if (a.bookName < b.bookName) {
                return -1;
            }
            if (a.bookName > b.bookName) {
                return 1;
            }
            return 0;
        }
        
        function compareCommentaries(a, b) {
            if (a.volume == b.volume) {
                if (a.topic < b.topic) {
                    return -1;
                }
                if (a.topic > b.topic) {
                    return 1;
                }
                return 0;
            }
            return parseInt(a.volume, 10) - parseInt(b.volume, 10);
        }

        function updateStatsTab(justUpdatedItem) {
            $("#stats-quiz-progress").html(overallStats.toString());
            var overallBibleStats = new Stats();
            var overallCommentaryStats = new Stats();
            var bibleStats = [];
            var commentaryStats = [];

            for (var key in chapterStats) {
                var item = chapterStats[key];
                var stats = item.stats;
                if (item.isBible) {
                    overallBibleStats.add(stats.earned, stats.possible);
                    bibleStats.push(item);
                }
                else {
                    overallCommentaryStats.add(stats.earned, stats.possible);
                    commentaryStats.push(item);
                }
            }

            var noBibleAnsweredMsg = "<em>No Bible questions have been answered yet.</em>";
            var noCommentaryAnsweredMsg = "<em>No SDA Bible Commentary questions have been answered yet.</em>";

            if (bibleStats.length > 0) {
                $("#bible-stats-quiz-progress").html(overallBibleStats.toString());
            }
            else {
                $("#bible-stats-quiz-progress").html(noBibleAnsweredMsg);
            }
            if (commentaryStats.length > 0) {
                $("#commentary-stats-quiz-progress").html(overallCommentaryStats.toString());
            }
            else {
                $("#commentary-stats-quiz-progress").html(noCommentaryAnsweredMsg);
            }

            // Ok, now have to figure out the individual stats
            // Sort the arrays, then output them
            bibleStats.sort(compareBooks);
            commentaryStats.sort(compareCommentaries);
            // just empty the divs. it's not going to take that long to reconstruct, and we're
            // lazy. it's probably just as fast to empty and reconstruct than to find 
            // each individual item and then edit it. *shrug*
            $("#individual-bible-stats").empty();
            $("#individual-commentary-stats").empty();

            var html = "";
            if (bibleStats.length > 0) {
                for (var key in bibleStats) {
                    var item = bibleStats[key];
                    html += "<li>";
                    html += "<b>" + item.bookName + " " + item.chapter + "</b>: " + item.stats.toString();
                    html += "</li>";
                }
                $("#individual-bible-stats").html(html);
            }
            else {
                $("#individual-bible-stats").html("<li>" + noBibleAnsweredMsg + "</li>");
            }
            html = "";
            if (commentaryStats.length > 0) {
                for (var key in commentaryStats) {
                    var item = commentaryStats[key];
                    html += "<li>";
                    html += "<b>Volume " + item.volume + " (" + item.topic + ")</b>: " + item.stats.toString();
                    html += "</li>";
                }
                $("#individual-commentary-stats").html(html);
            }
            else {
                $("#individual-commentary-stats").html("<li>" + noCommentaryAnsweredMsg + "</li>");
            }
            $("#stats-progress").show();
        }

        // load that quiz!
        loadQuiz();
    });
</script>

<?php include(dirname(__FILE__)."/footer.php"); ?>