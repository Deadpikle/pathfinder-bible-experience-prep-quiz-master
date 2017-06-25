
function pageString(startPage, endPage) {
    if (typeof endPage !== 'undefined' && endPage != null && endPage != "" && endPage > startPage) {
        return 'pp. ' + startPage + '-' + endPage;
    }
    else {
        return 'p. ' + startPage;
    }
}

function commentaryVolumeString(volume, startPage, endPage) {
    var str = 'Volume ' + volume;
    str += ', ' + pageString(startPage, endPage);
    return str;
}

function fixRequiredSelectorCSS() {
    $('select[required]').css({
        display: 'inline',
        position: 'absolute',
        float: 'left',
        padding: 0,
        margin: 0,
        border: '1px solid rgba(255,255,255,0)',
        height: 0, 
        width: 0,
        top: '2em',
        left: '3em'
    });
    $('select').each(function( index ) {
        $(this).on('mousedown', function(e) {
            e.preventDefault();
            this.blur();
            window.focus();
        });
    });
}

// https://stackoverflow.com/a/2548133/3938401
if (typeof String.prototype.endsWith !== 'function') {
    String.prototype.endsWith = function(suffix) {
        return this.indexOf(suffix, this.length - suffix.length) !== -1;
    };
}