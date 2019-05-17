<h3>Welcome back, <?= $_SESSION["Username"] ?>!</h3>

<?php if ($app->isGuest) { ?>
    <h4>You are currently browsing the site in guest mode. You will be unable to add, edit, or delete questions.</h4>
<?php } ?>

<div id="user-links">
    <div class="row">
        <div class="col s12 m4">
            <ul>
                <li class="home-buttons"><a class='btn waves-effect waves-light' href="<?= $app->yurl('/questions') ?>">Questions</a></li>
                <li class="home-buttons"><a class='btn waves-effect waves-light' href="quiz-setup.php">Quiz me!</a></li>
                <li class="home-buttons"><a class='btn waves-effect waves-light' href="active-clubs.php">Clubs</a></li>
                <li class="home-buttons"><a class='btn waves-effect waves-light' href="study-guides.php">Study Guides</a></li>
                <li class="home-buttons"><a class='btn waves-effect waves-light' href="settings.php">Settings</a></li>
            </ul>
        </div>
        <div class="col s12 m8">
            <?php output_home_sections($sections, false, $_SESSION["ConferenceID"]); ?>
        </div>
    </div>
</div>

<div id="extra-home-info">
    <div class="row">
        <p class="col s12 m8">
           Updates <b>February 16, 2019</b>:</p>
        <ul class="browser-default col s12 m9">
            <li>You can now have questions in English, Spanish, or French! You can also adjust the default question on the <a href="settings.php">Settings</a> page.</li>
        </ul>
    </div>
    <div class="row">
        <p class="col s12 m8">
           Updates <b>June 29, 2018</b>:</p>
        <ul class="browser-default col s12 m9">
            <li>Weighted question distribution</li>
            <li>Home page information can now have subtitles</li>
            <li>Miscellaneous fixes/adjustments</li>
        </ul>
    </div>
    <div class="row">
        <p class="col s12 m8">
            The website was given a huge upgrade on <b>March 11, 2018</b>! Some exciting updates include:</p>
        <ul class="browser-default col s12 m9">
            <li>Search for question by question text, answer, or by Chapter:Verse on the main questions page</li>
            <li>Flash card fill-in-the-blank answers can now be either the full sentence with answers in <b>bold</b> or just a comma-separated list of words</li>
            <li>You can now print flash cards that can be printed as front/back flash cards rather than having the question/answer to the left/right of each other (on the same page as one another)! Remember to print double sided or this option won't work correctly!</li>
            <li>When taking a quiz, you can now review questions that you've already answered along with info on whether you got those questions correct or not</li>
            <li>When taking a quiz, you can now view chapter-by-chapter statistics for how well you're doing on the quiz</li>
            <li>Lots of little fixes and tweaks here and there for an improved user experience</li>
            <li>Many behind-the-scenes updates for administrators</li>
        </ul>
    </div>
</div>