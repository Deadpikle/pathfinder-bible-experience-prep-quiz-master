<?php
    require_once(dirname(__FILE__).'/init-admin.php');
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<h2>Upload Study Guide</h2>

<div id="upload-study-guide">
    <form action="ajax/upload-file.php" method="post">
        <input type="hidden" name="club-id" value="<?= $clubID ?>"/>
        <div class="row">
            <div class="file-field input-field col s12 m6">
                <div class="btn blue">
                    <span>Choose Study Guide File</span>
                    <input type="file" id="file-upload" name="file-upload" accept=".pdf,application/pdf" required>
                </div>
                <div class="file-path-wrapper">
                    <input class="file-path validate" type="text">
                </div>
            </div>
            <div class="input-field col s12 m4">
                <input type="url" id="display-name" name="display-name" data-length="300" required/>
                <label for="display-name">Display Name</label>
            </div>
        </div>
        <button class="btn waves-effect waves-light submit" type="submit" name="action">Upload</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/../footer.php") ?>