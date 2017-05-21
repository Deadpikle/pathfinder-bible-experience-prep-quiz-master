<?php
    require_once(dirname(__FILE__)."/init.php");
    $sections = load_home_sections($pdo);
?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<h3>Welcome back, <?=$_SESSION["FirstName"]?>!</h3>

<div id="user-links">
    <div class="row">
        <div class="col s3">
            <ul>
                <li><a href="view-questions.php">View Questions</a></li>
                <li><a href="quiz-setup.php">Quiz me!</a></li>
            </ul>  
        </div>
        <div class="col s9">
            <?php output_home_sections($sections, FALSE); ?>
        </div>
    </div>
</div>

<?php include(dirname(__FILE__)."/footer.php") ?>