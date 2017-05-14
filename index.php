<?php

    require_once(dirname(__FILE__)."/init.php");

?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<h2>Welcome back, <?=$_SESSION["FirstName"]?>!</h2>

<?php include(dirname(__FILE__)."/footer.php") ?>