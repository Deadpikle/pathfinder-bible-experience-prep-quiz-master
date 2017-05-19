<?php

    require_once(dirname(__FILE__)."/init.php");

?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<h3>Welcome back, <?=$_SESSION["FirstName"]?>!</h3>

<div id="user-links">
    <ul>
        <li><a href="view-questions.php">View Questions</a></li>
        <li><a href="quiz-setup.php">Quiz me!</a></li>
    </ul>  
</div>

<?php include(dirname(__FILE__)."/footer.php") ?>