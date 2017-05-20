<?php
    // TODO: clickless reCaptcha

    require_once(dirname(__FILE__)."/init.php");

    if (isset($_GET["error"])) {
        $error = $_GET["error"];
    }

?>

<?php include("header.php"); ?>

<h2>Welcome!</h2>

<?php if (isset($error)) { ?>
        <p class="error-message"> <?=$error?> </p>
<?php } ?>

<form action="ajax/login-access-code.php" method="post">
    <div class="row">
        <div class="input-field col s12 m4">
            <input type="text" id="access-code" name="access-code" required/>
            <label for="access-code">Access code</label>
        </div>
    </div>
    <button class="btn waves-effect waves-light submit" type="submit" name="action">Login</button>
</form>

<?php include("footer.php") ?>