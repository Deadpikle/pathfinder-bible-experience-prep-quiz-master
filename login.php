<?php

?>

<?php include("header.php"); ?>

<h2>Login</h2>

<form action="/ajax/login-verify.php" method="post">
    <label for="access-code">Access Code: </label>
    <input type="text" name="access-code"/>
    <button>Login</button>
</form>
<?php include("footer.php") ?>