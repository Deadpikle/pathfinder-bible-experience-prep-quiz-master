<?php
    require_once(dirname(__FILE__)."/init-admin.php");

    if (!$isWebAdmin) {
        header("Location: $basePath/index.php");
        die();
    }

    if ($_GET["type"] == "update") {
        $query = '
            SELECT Name, URL, ContactName, ContactEmail
            FROM Conferences
            WHERE ConferenceID = ?';
        $stmt = $pdo->prepare($query);
        $conferenceID = $_GET["id"];
        $stmt->execute([$conferenceID]);
        $conference = $stmt->fetch();
        if ($conference == null) {
            die("Invalid conference id");
        }
        $name = $conference["Name"];
        $url = $conference["URL"];
        $contactName = $conference["ContactName"];
        $contactEmail = $conference["ContactEmail"];
        $postType = "update";
        $titleString = "Edit";
    }
    else {
        $name = "";
        $url = "";
        $contactName = "";
        $contactEmail = "";
        $postType = "create";
        $titleString = "Create";
    }
    
    $title = $titleString . ' Conference';
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href="./view-conferences.php">Back</a></p>

<h4><?= $titleString ?> Conference</h4>

<div id="edit-conference">
    <form action="ajax/save-conference-edits.php?type=<?= $postType ?>" method="post">
        <input type="hidden" name="conference-id" value="<?= $conferenceID ?>"/>
        <div class="row">
            <div class="input-field col s12 m4">
                <input type="text" id="name" name="name" value="<?= $name ?>" placeholder="Upper Columbia Conference" value="" 
                    required data-length="150"/>
                <label for="name">Conference Name</label>
            </div>
            <div class="input-field col s12 m4">
                <input type="url" id="url" name="url" value="<?= $url ?>" placeholder="https://uccsda.org" value="" 
                    required data-length="350"/>
                <label for="name">Website URL</label>
            </div>
        </div>
        <div class="row">
            <div class="input-field col s12 m4">
                <input type="text" id="contact-name" name="contact-name" value="<?= $contactName ?>" placeholder="First-Name Last-Name" value="" 
                    required data-length="150"/>
                <label for="contact-name">Contact Name</label>
            </div>
            <div class="input-field col s12 m4">
                <input type="email" id="contact-email" name="contact-email" value="<?= $contactEmail ?>" placeholder="person@somewhere.com" value="" 
                    required data-length="150"/>
                <label for="contact-name">Contact Email</label>
            </div>
        </div>
        <button class="btn waves-effect waves-light submit" type="submit" name="action">Save</button>
    </form>
</div>

<?php include(dirname(__FILE__)."/../footer.php"); ?>