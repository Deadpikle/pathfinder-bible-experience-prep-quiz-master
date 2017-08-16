<?php
    require_once(dirname(__FILE__).'/init-admin.php');
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<h2>Administrator Panel</h2>

<div id="admin-links">
    <h4>Club Administrator Functions</h4>
    <ul>
        <li class="home-buttons"><a class="btn waves-effect waves-light" href="view-users.php">Users</a></li>
    </ul>  
    <?php if ($isWebAdmin) { ?>
        <h4>Web Administrator Functions</h4>
        <ul>
            <li class="home-buttons"><a class="btn waves-effect waves-light" href="view-blankable-words.php">Blankable Words</a></li>
            <li class="home-buttons"><a class="btn waves-effect waves-light" href="view-home-sections.php">Home Page Info</a></li>
            <li class="home-buttons"><a class="btn waves-effect waves-light" href="view-clubs.php">Pathfinder Clubs</a></li>
            <li class="home-buttons"><a class="btn waves-effect waves-light" href="manage-study-guides.php">Study Guides</a></li>
        </ul>
    <?php } ?>
</div>

<?php include(dirname(__FILE__)."/../footer.php") ?>