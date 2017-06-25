
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

// https://stackoverflow.com/a/2548133/3938401
if (typeof String.prototype.endsWith !== 'function') {
    String.prototype.endsWith = function(suffix) {
        return this.indexOf(suffix, this.length - suffix.length) !== -1;
    };
}