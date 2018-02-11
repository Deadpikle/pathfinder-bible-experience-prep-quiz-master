<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    if (!$isWebAdmin) {
        header("Location: $basePath/index.php");
        die();
    }

    $query = '
        SELECT ConferenceID, Name, URL, ContactName, ContactEmail 
        FROM Conferences 
        ORDER BY Name';
    $stmt = $pdo->prepare($query);
    $stmt->execute([]);
    $conferences = $stmt->fetchAll();
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href=".">Back</a></p>

<h4>Conferences</h4>

<div id="create-conference">
    <a class="btn waves-effect waves-light" href="create-edit-conference.php?type=create">Create Conference</a>
</div>

<div id="conferences-div">
    <table class="striped responsive-table">
        <thead>
            <tr>
                <th>Conference Name</th>
                <th>URL</th>
                <th>Contact Name</th>
                <th>Contact Email</th>
                <th></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($conferences as $conference) { ?>
                    <tr>
                        <td><?= $conference["Name"] ?></td>
                        <td><?= $conference["URL"] ?></td>
                        <td><?= $conference["ContactName"] ?></td>
                        <td><?= $conference["ContactEmail"] ?></td>
                        <td><a class="waves-effect waves-light btn" href="create-edit-conference.php?type=update&id=<?=$conference['ConferenceID'] ?>">Edit Conference</a></td>
                        <td><a class="waves-effect waves-light btn red white-text" href="delete-conference.php?id=<?=$conference['ConferenceID'] ?>">Delete Conference</a></td>
                    </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>