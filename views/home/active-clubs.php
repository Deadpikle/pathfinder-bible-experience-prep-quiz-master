
<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href="<?= $app->yurl('/') ?>">Back</a></p>

<h3>Active Pathfinder Clubs</h3>

<p>Here is <?= $clubCount == 1 ? '' : 'a list of' ?> the <?= $clubCount ?> Pathfinder <?= $clubCount == 1 ? 'club' : 'clubs' ?> that <?= $clubCount == 1 ? 'has' : 'have' ?> been active on this website within the last 30 days:</p>

<div id="active-clubs">
    <ul class="browser-default">
        <?php 
            foreach ($clubs as $club) { 
                if ($club->url === null) {
        ?>
                    <li><a href="<?= $club->url ?>"><?= $club->name ?></a> (<?= $conferences[$club->conferenceID]->name ?>)</li>
            <?php } else { ?>
                    <li><?= $club->name ?> (<?= $conferences[$club->conferenceID]->name ?>)</li>
            <?php } ?>
        <?php } ?>
    </ul>
    <h4>Active Conferences</h4>
    <ul class="browser-default">
        <?php foreach ($conferences as $conference) { // loop through conferences for alphabetical order
                if (!isset($conferenceCounts[$conference->conferenceID])) {
                    continue;
                }
                $count = $conferenceCounts[$conference->conferenceID];
                if ($conference->url != null && $conference->url !== '') {
        ?>
            <li>
                <a href="<?= $conference->url ?>"><?= $conference->name ?></a> (<?= $count ?> Pathfinder <?= $count == 1 ? 'club' : 'clubs' ?>)
            </li>
            <?php } else { ?>
            <li>
                <?= $conference->name ?> (<?= $count ?> Pathfinder <?= $count == 1 ? 'club' : 'clubs' ?>)
            </li>
            <?php } ?>
        <?php } ?>
    </ul>
</div>
