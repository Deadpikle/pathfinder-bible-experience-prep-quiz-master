<?php
    require_once(dirname(__FILE__)."/init.php");

    $stmt = $pdo->query('
    SELECT QuestionID, Question, Answer, NumberPoints, IsFlagged, 
        bStart.Name AS StartBook, cStart.Number AS StartChapter, vStart.Number AS StartVerse,
        bEnd.Name AS EndBook, cEnd.Number AS EndChapter, vEnd.Number AS EndVerse
    FROM Questions q 
        JOIN Verses vStart ON q.StartVerseID = vStart.VerseID
        JOIN Chapters cStart on vStart.ChapterID = cStart.ChapterID
        JOIN Books bStart ON bStart.BookID = cStart.BookID

        LEFT JOIN Verses vEnd ON q.EndVerseID = vEnd.VerseID
        LEFT JOIN Chapters cEnd on vEnd.ChapterID = cEnd.ChapterID
        LEFT JOIN Books bEnd ON bEnd.BookID = cEnd.BookID
    ORDER BY Question, Answer, NumberPoints
    ');

?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<p><a href=".">Back</a></p>

<div id="create">
    <a href="add-edit-question.php?type=create">Add Question</a>
</div>

<div id="users-div">
    <table>
        <thead>
            <tr>
                <th>Question</th>
                <th>Answer</th>
                <th>Start Reference</th>
                <th>End Reference</th>
                <th>Number of Points</th>
                <th>Flagged?</th>
                <th>Edit</th>
                <th>Delete</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $stmt->fetch()) { ?>
                    <tr>
                        <td><?= $row["Question"] ?></td>
                        <td><?= $row["Answer"] ?></td>
                        <td><?= $row["StartBook"] ?>&nbsp;<?= $row["StartChapter"] ?>:<?= $row["StartVerse"] ?></td>
                        <td><?= $row["EndBook"] ?>&nbsp;<?= $row["EndChapter"] ?>:<?= $row["EndVerse"] ?></td>
                        <td><?= $row["NumberPoints"] ?></td>
                        <td><?= $row["IsFlagged"] ?></td>
                        <td><a href="add-edit-question.php?type=update&id=<?=$row['QuestionID'] ?>">Edit Question</a></td>
                        <td>
                            <a href="delete-question.php?id=<?=$row['QuestionID'] ?>">Delete Question</a>
                         </td>
                    </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php include(dirname(__FILE__)."/footer.php"); ?>