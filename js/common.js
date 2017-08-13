
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

function isBibleQuestion(type) {
    return type === "bible-qna" || type === "bible-qna-fill";
}

function isCommentaryQuestion(type) {
    return type === "commentary-qna" || type === "commentary-qna-fill";
}

function isFillInQuestion(type) {
    return type.indexOf("-fill") !== -1;
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

function createFillInInput(inputSelector, questionWords) {
    $element = $(inputSelector);
    for (var i = 0; i < questionWords.length; i++) {
        var wordData = questionWords[i];
        if (wordData.before !== "") {
            $element.append(wordData.before);
        }
        if (wordData.word !== "") {
            if (wordData.shouldBeBlanked) {
                var html = '<span><input class="browser-default fill-in-blank-input" type="text" value="" data-autosize-input=\'{ "space": 4 }\'></input></span>';
                $element.append(html);
            }
            else {
                $element.append(wordData.word);
            }
        }
        if (wordData.after !== "") {
            $element.append(wordData.after);
        }
        if (i != questionWords.length - 1 && wordData.after !== "...") {
            $element.append(" ");
        }
    }
}