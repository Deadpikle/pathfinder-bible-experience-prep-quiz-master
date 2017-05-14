<?php
    require_once("init-admin.php");
?>

<?php include("../header.php"); ?>

<h2>Admin Panel for ~<?=$_SESSION["FirstName"]?>~</h2>

<div id="admin-links">
    <ul>
        <li><a href="view-users.php">Users</a></li>
        <li><a href="view-questions.php">Questions</a></li>
    </ul>  
</div>

<?php include("../footer.php") ?>