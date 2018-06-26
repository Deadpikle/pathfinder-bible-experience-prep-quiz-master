<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    $title = 'Bible Fill In Questions';

    if (!$isWebAdmin) {
        header("Location: $basePath/index.php");
        die();
    }
    $currentYear = get_active_year($pdo)["YearID"];
    $query = '
        SELECT c.ChapterID, c.Number, b.Name, COUNT(q.QuestionID) AS QuestionCount
        FROM Questions q JOIN Verses v ON q.StartVerseID = v.VerseID 
            JOIN Chapters c ON c.ChapterID = v.ChapterID
            JOIN Books b ON b.BookID = c.BookID
        WHERE b.YearID = ?
              AND q.Type = "bible-qna-fill"
        GROUP BY c.ChapterID, c.Number
        ORDER BY b.Name, c.Number';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$currentYear]);
    $bookQuestionData = $stmt->fetchAll();

    $total = 0;
    foreach ($bookQuestionData as $item) {
        $total += (int)$item['QuestionCount'];
    }
    $questionString = $total == 1 ? 'question' : 'questions';
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href=".">Back</a></p>

<h4>Bible Fill in the Blank Questions</h4>

<p>There are a total of <b><?= $total ?></b> Bible fill in the blank <?= $questionString ?> in the system out of a maximum of 500.</p>

<?php if ($total > 0) { ?>
    <div id="bible-fill-in-div">
        <table class="striped">
            <thead>
                <tr>
                    <th>Chapter</th>
                    <th>Number of Fill In Questions</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookQuestionData as $data) { ?>
                    <?php $questionString = $data['QuestionCount'] == 1 ? 'question' : 'questions'; ?>
                    <tr>
                        <td><b><?= $data['Name'] ?>&nbsp;<?= $data['Number'] ?></b></td>
                        <td><?= $data['QuestionCount'] ?> <?= $questionString ?></li></td>
                        <td><a class="waves-effect waves-light btn red white-text" href="delete-bible-fill.php?type=chapter&id=<?= $data['ChapterID'] ?>">Delete</a></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        <div class="divider"></div>
        <a class="waves-effect waves-light btn red white-text" href="delete-bible-fill.php?type=all">Delete All Bible Fill In Questions</a>
    </div>
<?php } ?>

<script type="text/javascript">
    $(document).ready(function() {
        $('select').material_select();
        fixRequiredSelectorCSS();
    });
</script>

<?php include(dirname(__FILE__)."/../footer.php"); ?>