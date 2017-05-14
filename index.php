<?php

    require_once(dirname(__FILE__)."/init.php");

?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<h2>Welcome back, <?=$_SESSION["FirstName"]?>!</h2>

<div id="user-links">
    <ul>
        <li><a href="add-edit-question.php?type=create">Add Question</a></li>
    </ul>  
</div>

<?php include(dirname(__FILE__)."/footer.php") ?>