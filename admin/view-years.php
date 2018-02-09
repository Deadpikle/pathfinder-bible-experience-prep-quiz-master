<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    if (!$isWebAdmin) {
        header("Location: $basePath/index.php");
        die();
    }

    $params = [];
    $query = '
        SELECT YearID, Year, IsCurrent
        FROM Years
        ORDER BY Year';
    $yearStmt = $pdo->prepare($query);
    $yearStmt->execute($params);
    $years = $yearStmt->fetchAll();
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<!-- https://github.com/Dogfalo/materialize/issues/1376 -->
<style type="text/css">
    [type="checkbox"]:not(:checked), [type="checkbox"]:checked {
        position: static;
        left: 0px; 
        opacity: 1; 
    }
</style>

<p><a href=".">Back</a></p>

<h4>Years</h4>

<div class="" id="years-div">
    <div class="" id="create">
        <h5>Add Year</h5>
        <form action="ajax/add-year.php" method="post">
            <div class="row">
                <div class="input-field col s6 m4">
                    <input type="number" id="year" name="year" value="2018" required/>
                    <label for="year">Year</label>
                </div>
                <div class="input-field col s6 m4">
                    <button class="inline btn waves-effect waves-light submit" type="submit" name="action">Add Year</button>
                </div>
            </div>
        </form>
    </div>
    <div class="divider"></div>
    <div class="">
        <table class="striped">
            <thead>
                <tr>
                    <th>Year</th>
                    <th>Current Year?</th>
                    <th>Change Current Year</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                    foreach ($years as $year) { 
                        $isCurrent = $year["IsCurrent"];
                        $checkedText = $isCurrent ? "checked" : "";
                ?>
                        <tr>
                            <td><?= $year["Year"] ?></td>
                            <td><span><input type='checkbox' disabled <?= $checkedText ?>></input></span></td>
                            <td>
                                <?php if (!$isCurrent) { ?>
                                    <a class="waves-effect waves-light btn" href="set-current-year.php?id=<?= $year["YearID"] ?>">Make current</a>
                                <?php } ?>
                            </td>
                        </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>