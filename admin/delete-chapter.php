<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    if (!$isWebAdmin) {
        header("Location: $basePath/index.php");
        die();
    }

    $chapterID = $_GET["id"];
    $bookID = $_GET["bookID"];
    $query = '
        SELECT b.Name, c.Number, y.Year
        FROM Chapters c JOIN Books b ON c.BookID = b.BookID JOIN Years y ON b.YearID = y.YearID
        WHERE ChapterID = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute([$chapterID]);
    $chapter = $stmt->fetch();

    if ($chapter == NULL) {
        die("Invalid book id");
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $chapterID == $_POST["chapter-id"]) {
        $query = 'DELETE FROM Chapters WHERE ChapterID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([$chapterID]);
        header("Location: view-book-details.php?id=$bookID");
    }

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href="./view-book-details.php?id=<?= $bookID ?>">Back</a></p>

<div id="delete-book">
    <h4> Are you sure you want to remove chapter number <?= $chapter["Number"] ?> from the available chapter list for <?= $chapter["Name"] ?> (<?= $chapter["Year"] ?>)?</h4>
    <form method="post">
        <input type="hidden" name="chapter-id" value="<?= $chapterID ?>"/>
        <button class="btn waves-effect waves-light submit red white-text" type="submit" name="action">Remove Chapter</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>