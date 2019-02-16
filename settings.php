<?php
    require_once(dirname(__FILE__)."/init.php");
    
    $title = 'Settings';

    $languages = get_languages($pdo);
    $userLanguage = get_user_language($pdo);

    if ($isPostRequest) {
        $query = 'UPDATE Users SET PreferredLanguageID = ? WHERE UserID = ?';
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $_POST["language-select"], 
            $_SESSION["UserID"]
        ]);
        $_SESSION["PreferredLanguageID"] = $_POST["language-select"];
    }
?>
    
<?php include(dirname(__FILE__)."/header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href="./index.php">Back</a></p>

<?php if ($isPostRequest) { ?>
    <p>Settings successfully updated!</p>
<?php } ?>

<form method="post">
    <p><b>Adjust Default Quiz/Question Language</b></p>
    <div class="row">
        <div class="input-field col s12 m4">
            <select id="language-select" name="language-select">
                <?php foreach ($languages as $language) { 
                        $selected = $language["LanguageID"] == $userLanguage["LanguageID"] ? 'selected' : '';
                        $name = $language["Name"];
                        if ($language["AltName"] !== "") {
                            $name .= " (" . $language["AltName"] . ")";
                        }
                ?>
                    <option value="<?= $language['LanguageID'] ?>" <?= $selected ?>><?= $name ?></option>
                <?php } ?>
            </select>
            <label for="language-select">Filter by language</label>
        </div>
    </div>
    <button class="btn waves-effect waves-light submit" type="submit" name="action">Save</button>
</form>

<?php include(dirname(__FILE__)."/footer.php"); ?>


<script type="text/javascript">
    $(document).ready(function() {
        $("#language-select").material_select();
    });
</script>