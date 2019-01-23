<?php
    require_once(dirname(__FILE__)."/init.php");
    
    $title = 'Active Clubs';

    // https://stackoverflow.com/a/26044915/3938401 -- 30 days ago
    $thirtyDaysAgo = date('Y-m-d 00:00:00', strtotime('-31 days'));

    $query = '
        SELECT DISTINCT c.Name, c.URL, conf.Name AS ConferenceName
        FROM Users u JOIN Clubs c ON u.ClubID = c.ClubID
            LEFT JOIN Conferences conf ON conf.ConferenceID = c.ConferenceID
        WHERE LastLoginDate > ?
        ORDER BY c.Name';
    $stmt = $pdo->prepare($query);
    $params = [ $thirtyDaysAgo ];
    $stmt->execute($params);
    $clubs = $stmt->fetchAll();

    $conferences = [];
    foreach ($clubs as $club) {
        if (!isset($conferences[$club['ConferenceName']])) {
            $conferences[$club['ConferenceName']] = 1;
        } else {
            $conferences[$club['ConferenceName']] += 1;
        }
    }
    ksort($conferences);
?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href=".">Back</a></p>

<h3>Active Pathfinder Clubs</h3>

<p>Here is a list of Pathfinder clubs that have been active on this website within the last 30 days:</p>

<div id="active-clubs">
    <ul class="browser-default">
        <?php 
            foreach ($clubs as $club) { 
                if ($club["URL"] != NULL) {
        ?>
                    <li><a href="<?= $club['URL'] ?>"><?= $club["Name"] ?></a> (<?= $club["ConferenceName"] ?>)</li>
            <?php } else { ?>
                    <li><?= $club["Name"] ?></li>
            <?php } ?>
        <?php } ?>
    </ul>
    <h4>Active Conferences</h4>
    <ul class="browser-default">
        <?php foreach ($conferences as $conferenceName => $numberOfClubs) { ?>
            <li><?= $conferenceName ?> (<?= $numberOfClubs ?> Pathfinder <?= $numberOfClubs == 1 ? 'club' : 'clubs' ?>)</li>
        <?php } ?>
    </ul>
</div>

<?php include(dirname(__FILE__)."/footer.php") ?>