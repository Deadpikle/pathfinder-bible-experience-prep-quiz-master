<?php
    require_once(dirname(__FILE__)."/init-admin.php");
    
    $title = 'Books';

    if (!$isWebAdmin) {
        header("Location: $basePath/index.php");
        die();
    }

    $params = [];
    $query = '
        SELECT BookID, Name, NumberChapters, Year
        FROM Books b JOIN Years y ON b.YearID = y.YearID
        ORDER BY Year, Name';
    $bookStmt = $pdo->prepare($query);
    $bookStmt->execute($params);
    $books = $bookStmt->fetchAll();
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href=".">Back</a></p>

<h4>Bible Books</h4>

<div class="" id="books-div">
    <div class="" id="create">
        <h5>Add Book for Current Year (<?= $activeYearNumber ?>)</h5>
        <form action="ajax/add-book.php" method="post">
            <div class="row">
                <div class="input-field col s6 m4">
                    <input type="text" id="name" name="name" value="" placeholder="2 Kings" required data-length="150"/>
                    <label for="name">Name</label>
                </div>
                <div class="input-field col s6 m4">
                    <input type="number" id="number-chapters" name="number-chapters" placeholder="1" value="" required min="1" max="150"/>
                    <label for="number-chapters">Number of Chapters</label>
                </div>
                <div class="input-field col s6 m4">
                    <button class="inline btn waves-effect waves-light submit" type="submit" name="action">Add Book</button>
                </div>
            </div>
        </form>
    </div>
    <div class="divider"></div>
    <div class="">
        <table class="striped responsive-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Number of Chapters</th>
                    <th>Year</th>
                    <th></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                    foreach ($books as $book) { ?>
                        <tr>
                            <td><?= $book["Name"] ?></td>
                            <td><?= $book["NumberChapters"] ?></td>
                            <td><?= $book["Year"] ?></td>
                            <td>
                                <a class="waves-effect waves-light btn" href="view-book-details.php?id=<?= $book["BookID"] ?>">Manage Chapters</a>
                            </td>
                            <td>
                                <a class="waves-effect waves-light btn red white-text" href="delete-book.php?id=<?= $book["BookID"] ?>">Remove</a>
                            </td>
                        </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>