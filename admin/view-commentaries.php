<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    if (!$isWebAdmin) {
        header("Location: $basePath/index.php");
        die();
    }

    $params = [];
    $query = '
        SELECT CommentaryID, Number, Year, TopicName
        FROM Commentaries c JOIN Years y ON c.YearID = y.YearID
        ORDER BY Year, Number';
    $commentaryStmt = $pdo->prepare($query);
    $commentaryStmt->execute($params);
    $commentaries = $commentaryStmt->fetchAll();
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href=".">Back</a></p>

<h4>SDA Bible Commentaries</h4>

<div class="" id="commentaries-div">
    <div class="" id="create">
        <h5>Add Commentary for Current Year (<?= $activeYearNumber ?>)</h5>
        <form action="ajax/add-commentary.php" method="post">
            <div class="row">
                <div class="input-field col s6 m4">
                    <input type="number" id="commentary" name="commentary" value="" placeholder="1" required min="1" max="12"/>
                    <label for="commentary">Commentary Number</label>
                </div>
                <div class="input-field col s6 m4">
                    <input type="text" id="topic" name="topic" value="" placeholder="Daniel" required data-length="150"/>
                    <label for="topic">Commentary Topic</label>
                </div>
                <div class="input-field col s6 m4">
                    <button class="inline btn waves-effect waves-light submit" type="submit" name="action">Add Commentary</button>
                </div>
            </div>
        </form>
    </div>
    <div class="divider"></div>
    <div class="">
        <table class="striped responsive-table">
            <thead>
                <tr>
                    <th>Commentary Volume</th>
                    <th>Topic</th>
                    <th>Year</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                    foreach ($commentaries as $commentary) { ?>
                        <tr>
                            <td><?= $commentary["Number"] ?></td>
                            <td><?= $commentary["TopicName"] ?></td>
                            <td><?= $commentary["Year"] ?></td>
                            <td>
                                <a class="waves-effect waves-light btn red white-text" href="delete-commentary.php?id=<?= $commentary["CommentaryID"] ?>">Remove</a>
                            </td>
                        </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>