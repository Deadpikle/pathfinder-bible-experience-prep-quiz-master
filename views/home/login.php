<h2>Welcome!</h2>

<?php if (isset($error)) { ?>
    <p class="error-message"> <?= $error ?> </p>
<?php } ?>

<form method="post">
    <div class="row">
        <div class="input-field col s12 m4">
            <input type="password" id="access-code" name="access-code" required/>
            <label for="access-code">Access code</label>
        </div>
    </div>
    <button class="btn waves-effect waves-light submit" type="submit" name="action">Login</button>
</form>