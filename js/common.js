function commentaryVolumeString(volume, startPage, endPage) {
    var str = 'Volume ' + volume;
    if (typeof endPage !== 'undefined' && endPage != null && endPage != "" && endPage > startPage) {
        str += ', pp. ' + startPage + '-' + endPage;
    }
    else {
        str += ', p. ' + startPage;
    }
    return str;
}