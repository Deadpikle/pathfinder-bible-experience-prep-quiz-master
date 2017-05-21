<?php
    require_once(dirname(__FILE__).'/init-admin.php');
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<h2>Administrator Panel</h2>

<div id="admin-links">
    <ul>
        <li class="home-buttons"><a class="btn waves-effect waves-light" href="view-users.php">Users</a></li>
        <?php if ($isWebAdmin) { ?>
            <li class="home-buttons"><a class="btn waves-effect waves-light" href="view-home-sections.php">Home Page Info</a></li>
        <?php } ?>
    </ul>  
</div>

<?php include(dirname(__FILE__)."/../footer.php") ?>