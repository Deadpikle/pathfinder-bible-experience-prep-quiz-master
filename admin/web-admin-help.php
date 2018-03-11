<?php
    require_once(dirname(__FILE__).'/init-admin.php');
    
    if (!$isWebAdmin) {
        header("Location: $basePath/index.php");
        die();
    }

    $title = 'Web Admin Help';
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href="./">Back</a></p>

<h4>Web Administrator Help</h4>

<div id="admin-help">
    <div class="row">
        <div class="col s12 m8">
            <h5>Transitioning to a new PBE Year</h5>
            <p>The PBE quiz engine website is set up such that each year of PBE questions can be hosted on the same website without doing a large amount of work to transition in between years. Each year can use different Bible books, commentary volumes, study guides, and quiz questions. Users, conferences, and administrator users remain the same. Admins can always view which books, commentaries, and study guides are available from the admin interface, but users who are not website administrators can only view the current year's information.</p>
            <p></p>
        </div>
    </div>
</div>

<?php include(dirname(__FILE__)."/../footer.php") ?>