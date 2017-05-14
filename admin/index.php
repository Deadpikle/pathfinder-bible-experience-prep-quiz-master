<?php
    require_once(dirname(__FILE__).'/init-admin.php');
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<h2>Admin Panel for ~<?=$_SESSION["FirstName"]?>~</h2>

<div id="admin-links">
    <ul>
        <li><a href="view-users.php">Users</a></li>
        <li><a href="view-questions.php">Questions</a></li>
    </ul>  
</div>

<?php include(dirname(__FILE__)."/../footer.php") ?>