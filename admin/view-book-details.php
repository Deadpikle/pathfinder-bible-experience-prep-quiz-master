<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    if (!$isWebAdmin) {
        header("Location: $basePath/index.php");
        die();
    }

    $bookID = $_GET["id"];

    // load book data
    $params = [$bookID];
    $query = '
        SELECT BookID, Name, Year
        FROM Books b JOIN Years y ON b.YearID = y.YearID
        WHERE BookID = ?';
    $bookStmt = $pdo->prepare($query);
    $bookStmt->execute($params);
    $books = $bookStmt->fetchAll();
    if (count($books) == 0) {
        die("Invalid book ID");
    }
    $book = $books[0];
    $bookID = $book["BookID"];
    $bookName = $book["Name"];
    $bookYear = $book["Year"];

    $params = [$bookID];
    $query = '
        SELECT ChapterID, Number, NumberVerses, b.Name
        FROM Chapters c JOIN Books b ON c.BookID = b.BookID
        WHERE c.BookID = ?
        ORDER BY Number';
    $chapterStmt = $pdo->prepare($query);
    $chapterStmt->execute($params);
    $chapters = $chapterStmt->fetchAll();
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href=".">Back</a></p>

<h4>Chapters for <?= $book["Name"] ?></h4>

<div class="" id="books-div">
    <div class="" id="create">
        <h5>Add Chapter for <?= $bookName ?> (<?= $bookYear ?>)</h5>
        <form action="ajax/add-chapter.php" method="post">
            <div class="row">
                <input type="hidden" id="book-id" name="book-id" value="<?= $bookID ?>" required/>
                <div class="input-field col s6 m4">
                    <input type="number" id="chapter-number" name="chapter-number" placeholder="1" value="" required min="1" max="150"/>
                    <label for="chapter-number">Chapter Number</label>
                </div>
                <div class="input-field col s6 m4">
                    <input type="number" id="number-verses" name="number-verses" placeholder="1" value="" required min="1" max="176"/>
                    <label for="number-verses">Number of Verses</label>
                </div>
                <div class="input-field col s6 m4">
                    <button class="inline btn waves-effect waves-light submit" type="submit" name="action">Add Chapter</button>
                </div>
            </div>
        </form>
    </div>
    <div class="divider"></div>
    <div class="">
        <table class="striped responsive-table">
            <thead>
                <tr>
                    <th>Chapter Number</th>
                    <th>Number of Verses</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                    foreach ($chapters as $chapter) { ?>
                        <tr>
                            <td><?= $chapter["Number"] ?></td>
                            <td><?= $chapter["NumberVerses"] ?></td>
                            <td>
                                <a class="waves-effect waves-light btn red white-text" href="delete-chapter.php?id=<?= $chapter["ChapterID"] ?>&bookID=<?= $bookID ?>">Remove</a>
                            </td>
                        </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>