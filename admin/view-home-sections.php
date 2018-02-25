<?php
    require_once(dirname(__FILE__)."/init-admin.php");
    
    $title = 'Home Info';
    
    if ($isClubAdmin) {
        header("Location: index.php");
    }

    $sections = load_home_sections($pdo, $_SESSION["ConferenceID"]);

    $params = [];
    $query = '
        SELECT YearID, Year, IsCurrent
        FROM Years
        ORDER BY Year';
    $yearStmt = $pdo->prepare($query);
    $yearStmt->execute($params);
    $years = $yearStmt->fetchAll();
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a class="btn-flat blue-text waves-effect waves-blue no-uppercase" href=".">Back</a></p>


<div id="sections-div">
    <div class="section" id="create">
        <h5>Create Section</h5>
        <form action="ajax/save-section-edits.php?type=create" method="post">
            <div class="row">
                <div class="input-field col s6 m4">
                    <input type="text" id="section-name" name="section-name" value="" required data-length="150"/>
                    <label for="section-name">Section Name</label>
                </div>
                <div class="input-field col s6 m4">
                    <button class="inline btn waves-effect waves-light submit" type="submit" name="action">Create Section</button>
                </div>
            </div>
        </form>
    </div>
    <div class="divider"></div>
    <div class="section">
        <h5>Copy Sections from Past/Admins</h5>
        <p>If you'd like to copy over the home info sections from a previous year or from the website admins, choose the year to copy from and click the applicable button. This will not overwrite any of your current information that you've set up for the current year (<?= $activeYearNumber ?>).</p>
        <form id="copy-form" method="post">
            <div class="row">
                <div class="input-field col s4 m2">
                    <select id="year" name="year" required>
                        <?php foreach ($years as $year) { 
                                $selectedText = $year["IsCurrent"] == 1 ? "selected" : "";
                            ?>
                            <option value="<?= $year["YearID"] ?>" <?= $selectedText ?>><?= $year["Year"] ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="input-field col s12 m10">
                    <button id="import-from-conference" class="margin-button btn waves-effect waves-light submit">Import from Conference</button>
                    <button id="import-from-admin" class="margin-button btn waves-effect waves-light submit">Import from Admin</button>
                </div>
            </div>
            <div class="div-below-selector row">
            </div>
        </form>
    </div>
    <div class="section" id="section-list">
        <h5>Modify Sections</h5>
        <p>You can drag and drop lines and line items to resort them.</p>
        <a id="save-sort" class="btn btn-flat teal-text">Save Sorted Items</a>
        <div class="sortable">
            <?php 
                output_home_sections($sections, TRUE);
            ?>
        </div>
    </div>
</div>

<div id="saved-modal" class="modal">
    <div class="modal-content">
        <h4>Section order saved!</h4>
    </div>
    <div class="modal-footer">
        <a href="#!" class="modal-action modal-close waves-effect waves-teal teal-text btn-flat">OK</a>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $('#saved-modal').modal();
        $('select').material_select();
        fixRequiredSelectorCSS();
        sortable('.sortable', {
            forcePlaceholderSize: true,
            placeholderClass: 'teal lighten-5',
        });
        $('#save-sort').on("click",function() {
            var sections = [];
            $('.sortable-item').each(function(index, element) {
                console.log(element.id);
                var sectionObj = {
                    id: element.id.replace('section-', ''),
                    index: index
                };
                sections.push(sectionObj)
            });
            $.ajax({
                type: "POST",
                url: "ajax/save-section-sorting.php",
                data: {
                    json: JSON.stringify(sections)
                },
                success: function(msg) {
                    $('#saved-modal').modal('open');
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    alert(thrownError);
                }
            });
        });
        $('#import-from-conference').on("click",function() {
            $('#copy-form').attr('action', 'ajax/import-home-info-from-conference.php');
            $('#copy-form').submit();
        });
        $('#import-from-admin').on("click",function() {
            $('#copy-form').attr('action', 'ajax/import-home-info-from-admin.php');
            $('#copy-form').submit();
        });
    });
</script>

<?php include(dirname(__FILE__)."/../footer.php"); ?>