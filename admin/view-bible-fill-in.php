<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    $title = 'Bible Fill In Questions';

    if (!$isWebAdmin) {
        header("Location: $basePath/index.php");
        die();
    }
    $currentYear = get_active_year($pdo)["YearID"];
    $query = '
        SELECT b.BookID, b.Name, COUNT(q.QuestionID) AS QuestionCount
        FROM Questions q JOIN Verses v ON q.StartVerseID = v.VerseID 
            JOIN Chapters c ON c.ChapterID = v.ChapterID
            JOIN Books b ON b.BookID = c.BookID
        WHERE b.YearID = ' . $currentYear . ' 
              AND q.Type = "bible-qna-fill"
        GROUP BY b.BookID
        ORDER BY b.Name';
    $stmt = $pdo->prepare($query);
    $stmt->execute([]);
    $bookQuestionData = $stmt->fetchAll();

    $total = 0;
    foreach ($bookQuestionData as $item) {
        $total += (int)$item['QuestionCount'];
    }
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href=".">Back</a></p>

<h4>Bible Fill in the Blank Questions</h4>

<p>There are a total of <b><?= $total ?></b> fill in the blank Bible questions in the system out of a maximum of 500.</p>

<div id="bible-fill-in-div">
    <ul>
        <?php foreach ($bookQuestionData as $data) { ?>
            <li><b><?= $data['Name'] ?></b>  -  <?= $data['QuestionCount'] ?> questions</li>
        <?php } ?>
    </ul>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $('select').material_select();
        fixRequiredSelectorCSS();
    });
</script>

<?php include(dirname(__FILE__)."/../footer.php"); ?>