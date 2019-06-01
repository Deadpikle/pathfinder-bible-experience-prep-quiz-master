
<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href="<?= $app->yurl('/') ?>">Back</a></p>

<h2>Study Guides</h2>

<div id="view-study-guides">
    <?php if (count($guides) === 0) { ?>
        <p>There are no study guides available at this time.</p>
    <?php } else { ?>
        <ul class="browser-default">
            <?php foreach ($guides as $guide) { ?>
                <li>
                    <td>
                        <a target="_blank" class="btn-flat blue-text waves-effect waves-blue no-uppercase" 
                            href="<?= $app->yurl('/' .  $guide->fileName) ?>">
                            <?= $guide->displayName ?>
                        </a>
                    </td> 
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
</div>
