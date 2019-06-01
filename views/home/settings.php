   
<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href="<?= $app->yurl('/') ?>">Back</a></p>

<?php if ($didUpdate) { ?>
    <p>Settings successfully updated!</p>
<?php } ?>

<form method="post">
    <p><b>Adjust Default Quiz/Question Language</b></p>
    <div class="row">
        <div class="input-field col s12 m4">
            <select id="language-select" name="language-select">
                <?php foreach ($languages as $language) {
                        $selected = $language->languageID == $userLanguage->languageID ? 'selected' : '';
                ?>
                    <option value="<?= $language->languageID ?>" <?= $selected ?>><?= $language->getDisplayName() ?></option>
                <?php } ?>
            </select>
            <label for="language-select">Filter by language</label>
        </div>
    </div>
    <button class="btn waves-effect waves-light submit" type="submit" name="action">Save</button>
</form>

<script type="text/javascript">
    $(document).ready(function() {
        $("#language-select").material_select();
    });
</script>