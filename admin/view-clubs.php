<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    if (!$isAdmin) {
        header("Location: $basePath/index.php");
        die();
    }
    $query = '
        SELECT ClubID, c.Name AS ClubName, c.URL, conf.Name AS ConferenceName
        FROM Clubs c LEFT JOIN Conferences conf ON c.ConferenceID = conf.ConferenceID
        ORDER BY conf.Name, c.Name';
    $stmt = $pdo->prepare($query);
    $stmt->execute([]);
    $clubs = $stmt->fetchAll();

    if ($isWebAdmin) {
        // need to let user choose which conference the club belongs to
        $query = 'SELECT ConferenceID, Name FROM Conferences ORDER BY Name';
        $stmt = $pdo->prepare($query);
        $stmt->execute([]);
        $conferences = $stmt->fetchAll();
    }
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href=".">Back</a></p>

<h4>Pathfinder Clubs</h4>

<div id="users-div">
    <div class="section" id="create">
        <h5>Create Club</h5>
        <form action="ajax/save-club-edits.php?type=create" method="post">
            <div class="row">
                <div class="input-field col s12 m4">
                    <input type="text" id="club-name" name="club-name" value="" required data-length="150"/>
                    <label for="club-name">Club Name</label>
                </div>
                <div class="input-field col s12 m4">
                    <input type="url" id="club-url" name="club-url" value="" required data-length="300"/>
                    <label for="club-url">Website or Facebook URL</label>
                </div>
                <?php if (!$isWebAdmin) { ?>
                    <div class="input-field col s12 m4">
                        <button class="inline btn waves-effect waves-light submit" type="submit" name="action">Create Club</button>
                    </div>
                <?php } ?>
            </div>
            <?php if ($isWebAdmin) { ?>
                <div class="row">
                    <div class="input-field col s12 m4">
                        <select id="conference" name="conference" required>
                            <option id="conference-no-selection-option" value="">Select a conference...</option>
                            <?php foreach ($conferences as $conference) {  ?>
                                <option value="<?= $conference['ConferenceID'] ?>"><?=$conference['Name']?></option>
                            <?php } ?>
                        </select>
                        <label>Conference</label>
                    </div>
                    <div class="input-field col s12 m4">
                        <button class="inline btn waves-effect waves-light submit" type="submit" name="action">Create Club</button>
                    </div>
                </div>
            <?php } ?>
        </form>
    </div>
    <div class="divider"></div>
    <table class="striped responsive-table">
        <thead>
            <tr>
                <th>Club Name</th>
                <th>URL</th>
                <th>Conference</th>
                <th>Edit</th>
                <th>Delete</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clubs as $club) { ?>
                    <tr>
                        <td><?= $club["ClubName"] ?></td>
                        <td><?= $club["URL"] ?></td>
                        <td><?= $club["ConferenceName"] ?></td>
                        <td><a href="create-edit-club.php?type=update&id=<?=$club['ClubID'] ?>">Edit Club</a></td>
                        <td><a href="delete-club.php?id=<?=$club['ClubID'] ?>">Delete Club</a></td>
                    </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $('select').material_select();
        fixRequiredSelectorCSS();
    });
</script>

<?php include(dirname(__FILE__)."/../footer.php"); ?>