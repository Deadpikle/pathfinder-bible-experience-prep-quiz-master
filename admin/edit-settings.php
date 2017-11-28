<?php

// TODO:
// Error messages if server fails

    require_once(dirname(__FILE__)."/init-admin.php");

    if ($isClubAdmin) {
        die("invalid user type");
    }

    $didJustSave = isset($_GET["saved"]);

    $query = '
        SELECT SettingID, SettingKey, SettingValue, DisplayName
        FROM Settings
        ORDER BY DisplayName';
    $settingsStmt = $pdo->prepare($query);
    $settingsStmt->execute([]);
    $settings = $settingsStmt->fetchAll();

?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href=".">Back</a></p>

<?php if ($didJustSave) { ?>
    <h6>Settings saved!</h6>
<?php } ?>

<h4>Edit Website Settings</h4>

<div id="edit-settings">
    <form action="ajax/save-setting-edits.php" method="post">
        <?php foreach ($settings as $setting) { ?>
            <div class="row">
                <div class="input-field col s12 m4">
                    <input type="text" id="<?= $setting['SettingID'] ?>" name="<?= $setting['SettingID'] ?>" 
                    value="<?= $setting['SettingValue'] ?>" required data-length="150"/>
                    <label for="<?= $setting['SettingID'] ?>"><?= $setting['DisplayName'] ?></label>
                </div>
            </div>
        <?php } ?>
        <button class="btn waves-effect waves-light submit" type="submit" name="action">Save</button>
    </form>
</div>

<script type="text/javascript">
    $(document).ready(function() {
    });
</script>

<?php include(dirname(__FILE__)."/../footer.php"); ?>