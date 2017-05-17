<?php

    require_once(dirname(__FILE__)."/init.php");

    if (isset($_GET["error"])) {
        $error = $_GET["error"];
    }

?>

<?php include("header.php"); ?>

<h2>Login</h2>

<?php if (isset($error)) { ?>
        <p class="error-message"> <?=$error?> </p>
<?php } ?>

<form action="ajax/login-access-code.php" method="post">
    <label for="access-code">Access Code: </label>
    <input type="text" name="access-code"/>
    <button>Login</button>
</form>

<?php include("footer.php") ?>