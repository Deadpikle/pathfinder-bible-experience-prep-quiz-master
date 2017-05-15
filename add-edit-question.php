<?php
    require_once(dirname(__FILE__)."/init.php");

    if ($_GET["type"] == "update") {
        $query = 'SELECT Question, Answer, NumberPoints, IsFlagged, StartVerseID, LastVerseID FROM Questions WHERE QuestionID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_GET["id"]]);
        $question = $stmt->fetch();
        $questionText = $question["Question"];
        $answer = $question["Answer"];
        $numberOfPoints = $question["NumberPoints"];
        $isFlagged = $question["IsFlagged"];
        $postType = "update";
    }
    else {
        $questionText = "";
        $answer = "";
        $numberOfPoints = "";
        $isFlagged = FALSE;
        $postType = "create";
    }

    $bookQuery = 'SELECT BookID, Name, NumberChapters, YearID FROM Books ORDER BY Name';
    $books = $pdo->query($bookQuery)->fetchAll();
?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<p><a href="./index.php" class="back mdl-button mdl-js-button mdl-js-ripple-effect mdl-button--raised mdl-button--accent">Back</a></p>

<div id="edit-question">
    <form action="ajax/save-question-edits.php?type=<?= $postType ?>" method="post">
        <input type="hidden" name="question-id" value="<?= $GET['id'] ?>"/>
        <p>
            <div class="mdl-textfield mdl-js-textfield">
                <textarea class="mdl-textfield__input" type="text" rows="5" id="question-text" name="question-text" ></textarea>
                <label class="mdl-textfield__label" for="question-text">Question Text</label>
            </div>
        </p>
        <p>
            <div class="mdl-textfield mdl-js-textfield">
                <textarea class="mdl-textfield__input" type="text" rows="5" id="question-answer" name="question-answer" ></textarea>
                <label class="mdl-textfield__label" for="question-answer">Answer</label>
            </div>
        </p>
        <p>
            <div class="mdl-textfield mdl-js-textfield">
                <input class="mdl-textfield__input" type="text" pattern="-?[0-9]*(\.[0-9]+)?" id="number-of-points" name="number-of-points">
                <label class="mdl-textfield__label" for="number-of-points">Number of Points</label>
                <span class="mdl-textfield__error">Input is not a number!</span>
            </div>
        </p>
        <p>

            <div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label getmdl-select getmdl-select__fullwidth">
                <input class="mdl-textfield__input" type="text" id="book-select" value="Select a book" readonly tabIndex="-1">
                <label for="book-select">
                    <i class="mdl-icon-toggle__label material-icons">keyboard_arrow_down</i>
                </label>
                <label for="book-select" class="mdl-textfield__label">Book</label>
                <ul for="book-select" class="mdl-menu mdl-menu--bottom-left mdl-js-menu">
                    <?php foreach ($books as $book) { ?>
                        <li class="mdl-menu__item" data-val="<?=$book['BookID']?>"><?=$book["Name"]?></li>
                    <?php } ?>
                </ul>
            </div>  

            <label for="book">Verse Setup </label>
            <select name="book">
                <option value="-1">Select a book...</option>
                <?php foreach ($books as $book) { ?>
                        <option value="<?=$book['BookID']?>"><?=$book["Name"]?></option>
                <?php } ?>
            </select>
        </p>
        <p>
            <input type="submit" value="Save" class="back mdl-button mdl-js-button mdl-js-ripple-effect mdl-button--raised mdl-button--colored"/>
        </p>
    </form>
</div>

<?php include(dirname(__FILE__)."/footer.php"); ?>