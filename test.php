<?php
    require_once(dirname(__FILE__)."/init.php");
    $sections = load_home_sections($pdo);
?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<div id="user-links">
    <p>Hello, <span contenteditable="true" class="browser-default fill-in-blank-input"></span>! <span class="only-bottom-border fill-in-blank-input" contenteditable="true">testificate</span> </p>
</div>
<p></p>
<div id="user-links">
    <p>Hello, <span><input class="browser-default fill-in-blank-input" type="text" value=""  data-autosize-input='{ "space": 4 }' /></span>!</p>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        // denying line breaks (enter key): https://stackoverflow.com/a/428139/3938401
        $("span").keypress(function(e) { return e.which != 13; } );
    });

</script>

<?php include(dirname(__FILE__)."/footer.php") ?>