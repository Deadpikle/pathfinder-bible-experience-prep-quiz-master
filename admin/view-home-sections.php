<?php
    require_once(dirname(__FILE__)."/init-admin.php");
    if ($isClubAdmin) {
        header("Location: index.php");
    }

    $sections = load_home_sections($pdo);
?>

<?php include(dirname(__FILE__)."/../header.php"); ?>

<p><a href=".">Back</a></p>


<div id="sections-div">
    <div class="section" id="create">
        <h5>Create Section</h5>
        <form action="ajax/save-section-edits.php?type=create" method="post">
            <div class="row">
                <div class="input-field col s6 m4">
                    <input type="text" id="section-name" name="section-name" value="" required/>
                    <label for="section-name">Section Name</label>
                </div>
                <div class="input-field col s6 m4">
                    <button class="inline btn waves-effect waves-light submit" type="submit" name="action">Create Section</button>
                </div>
            </div>
        </form>
    </div>
    <div class="divider"></div>
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
    $('#saved-modal').modal();
    $(document).ready(function() {
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
    });
</script>

<?php include(dirname(__FILE__)."/../footer.php"); ?>