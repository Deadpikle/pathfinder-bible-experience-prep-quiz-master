<?php
    require_once(dirname(__FILE__)."/init.php");

    $stmt = $pdo->query('
    SELECT QuestionID, Question, Answer, NumberPoints, IsFlagged, b.Name AS Book, c.Number AS Chapter, v.Number AS Verse
    FROM Questions q 
        JOIN Verses v ON q.StartVerseID = v.VerseID
        JOIN Chapters c on v.ChapterID = c.ChapterID
        JOIN Books b ON b.BookID = c.BookID
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
                <th>Reference</th>
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
                        <td><?= $row["Book"] ?>&nbsp;<?= $row["Chapter"] ?>:<?= $row["Verse"] ?></td>
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