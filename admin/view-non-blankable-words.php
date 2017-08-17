<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    if ($isClubAdmin || !$isWebAdmin) {
        header("Location: index.php");
    }
    $query = 'SELECT WordID, Word FROM BlankableWords ORDER BY Word';
    $stmt = $pdo->prepare($query);
    $stmt->execute([]);
    $words = $stmt->fetchAll();
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href=".">Back</a></p>

<div id="words-div">
    <div class="section" id="create">
        <h5>Add Non-Blankable Word</h5>
        <form action="ajax/save-blankable-word-edits.php?type=create" method="post">
            <div class="row">
                <div class="input-field col s4 m4">
                    <input type="text" id="blankable-word" name="blankable-word" value="" required data-length="150"/>
                    <label for="blankable-word">Non-Blankable Word</label>
                </div>
                <div class="input-field col s4 m4">
                    <button class="inline btn waves-effect waves-light submit" type="submit" name="action">Add Word</button>
                </div>
            </div>
        </form>
    </div>
    <div class="divider"></div>
    <?php if (count($words) > 0) { ?>
        <table class="striped">
            <thead>
                <tr>
                    <th>Word</th>
                    <th>Edit</th>
                    <th>Delete</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($words as $word) { ?>
                        <tr>
                            <td><?= $word["Word"] ?></td>
                            <td><a href="create-edit-word.php?type=update&id=<?=$word['WordID'] ?>">Edit Word</a></td>
                            <td><a href="delete-word.php?id=<?=$word['WordID'] ?>">Delete Word</a></td>
                        </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>