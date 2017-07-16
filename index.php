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
                <li class="home-buttons"><a class='btn  waves-effect waves-light' href="active-clubs.php">Clubs</a></li>
                <li class="home-buttons"><a class='btn  waves-effect waves-light' href="study-guides.php">Study Guides</a></li>
            </ul>
        </div>
        <div class="col s8">
            <?php output_home_sections($sections, FALSE); ?>
        </div>
    </div>
</div>

<div id="extra-home-info">
    <div class="row">
        <p class="col s12">
        Please remember that this website is under active construction. New features and adjustments may appear any day! If you encounter issues or need assistance, please see <a href="about.php">the about page</a> for this website.
        </p>
    </div>
</div>

<?php include(dirname(__FILE__)."/footer.php") ?>