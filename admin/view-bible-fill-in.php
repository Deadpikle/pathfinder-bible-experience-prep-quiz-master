<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    $title = 'Bible Fill In Questions';

    if (!$isWebAdmin) {
        header("Location: $basePath/index.php");
        die();
    }
    $currentYear = get_active_year($pdo)["YearID"];
    $query = '
        SELECT c.ChapterID, c.Number, b.Name, COUNT(q.QuestionID) AS QuestionCount, q.LanguageID
        FROM Questions q JOIN Verses v ON q.StartVerseID = v.VerseID 
            JOIN Chapters c ON c.ChapterID = v.ChapterID
            JOIN Books b ON b.BookID = c.BookID
        WHERE b.YearID = ?
              AND q.Type = "bible-qna-fill"
        GROUP BY c.ChapterID, c.Number, q.LanguageID
        ORDER BY b.Name, c.Number, q.LanguageID';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$currentYear]);
    $bookQuestionData = $stmt->fetchAll();

    $totals = [];
    foreach ($bookQuestionData as $item) {
        if (!isset($totals[$item["LanguageID"]])) {
            $totals[$item["LanguageID"]] = 0;
        }
        $totals[$item["LanguageID"]] += (int)$item['QuestionCount'];
    }
    $questionString = 0 == 1 ? 'question' : 'questions';
    $languages = get_languages($pdo);
    $languagesByID = [];
    $hasSomeLanguageData = false;
    foreach ($languages as $language) {
        $languagesByID[$language["LanguageID"]] = $language;
        if (!isset($totals[$language["LanguageID"]])) {
            $totals[$language["LanguageID"]] = 0;
        } else {
            $hasSomeLanguageData = true;
        }
    }
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href=".">Back</a></p>

<h4>Bible Fill in the Blank Questions</h4>


<?php foreach ($languages as $language) { 
        $isAreString = $totals[$language["LanguageID"]] == 1 ? 'is' : 'are';
        $questionString = $totals[$language["LanguageID"]] == 1 ? 'question' : 'questions';
?>
    <p>There <?= $isAreString ?> a total of <b><?= $totals[$language["LanguageID"]] ?> <?= language_display_name($language) ?></b> Bible fill in the blank <?= $questionString ?> in the system out of a maximum of 500.</p>
<?php } ?>

<?php if ($hasSomeLanguageData > 0) { ?>
    <div id="bible-fill-in-div">
        <table class="striped">
            <thead>
                <tr>
                    <th>Chapter</th>
                    <th>Language</th>
                    <th>Number of Fill In Questions</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookQuestionData as $data) { 
                        $questionString = $data['QuestionCount'] == 1 ? 'question' : 'questions';
                ?>
                    <tr>
                        <td><b><?= $data['Name'] ?>&nbsp;<?= $data['Number'] ?></b></td>
                        <td><b><?= language_display_name($languagesByID[$data['LanguageID']]) ?></b></td>
                        <td><?= $data['QuestionCount'] ?> <?= $questionString ?></li></td>
                        <td><a class="waves-effect waves-light btn red white-text" href="delete-bible-fill.php?type=chapter&id=<?= $data['ChapterID'] ?>&languageID=<?= $data['LanguageID'] ?>">Delete</a></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        <div class="divider"></div>

        <?php foreach ($languages as $language) { ?>
            <p><a class="waves-effect waves-light btn red white-text" href="delete-bible-fill.php?type=all&languageID=<?= $language['LanguageID'] ?>">Delete All <?= language_display_name($language) ?> Bible Fill In Questions</a></p>
        <?php } ?>
    </div>
<?php } ?>

<script type="text/javascript">
    $(document).ready(function() {
        $('select').material_select();
        fixRequiredSelectorCSS();
    });
</script>

<?php include(dirname(__FILE__)."/../footer.php"); ?>