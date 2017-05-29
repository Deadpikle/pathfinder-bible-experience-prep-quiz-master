<?php
    require_once(dirname(__FILE__)."/init.php");
    $sections = load_home_sections($pdo);
?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<h3>Welcome back, <?=$_SESSION["Username"]?>!</h3>

<div id="user-links">
    <div class="row">
        <div class="col s4">
            <ul>
                <li class="home-buttons"><a class='btn  waves-effect waves-light' href="view-questions.php">Questions</a></li>
                <li class="home-buttons"><a class='btn  waves-effect waves-light' href="quiz-setup.php">Quiz me!</a></li>
            </ul>
        </div>
        <div class="col s8">
            <?php output_home_sections($sections, FALSE); ?>
        </div>
    </div>
</div>

<?php include(dirname(__FILE__)."/footer.php") ?>