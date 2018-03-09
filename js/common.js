
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
        left: '3em',
        pointerEvents: 'none'
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
    $element.append(fillInText(questionWords));
}

// if shouldBoldWords is true, puts in answers as bold instead of as blanks
function fillInText(questionWords, shouldBoldWords, shouldAvoidInputFields = false) {
    if (shouldBoldWords === undefined) {
        shouldBoldWords = false;
    }
    var output = '';
    for (var i = 0; i < questionWords.length; i++) {
        var wordData = questionWords[i];
        if (wordData.before !== "") {
            output += wordData.before;
        }
        if (wordData.word !== "") {
            if (wordData.shouldBeBlanked) {
                if (shouldBoldWords) {
                    var html = '<b>' + wordData.word + '</b>';
                    output += html;
                }
                else if (shouldAvoidInputFields) {
                    output += '________';
                }
                else {
                    var html = '<span><input class="browser-default fill-in-blank-input" type="text" value="" data-autosize-input=\'{ "space": 4 }\'></input></span>';
                    output += html;
                }
            }
            else {
                output += wordData.word;
            }
        }
        if (wordData.after !== "") {
            output += wordData.after;
        }
        if (i != questionWords.length - 1 && wordData.after !== '...') {
            output += ' ';
        }
    }
    return output;
}

function fillInAnswerString(questionWords, separator) {
    if (separator === undefined) {
        separator = ', ';
    }
    var output = '';
    var didAddOneToList = false;
    for (var i = 0; i < questionWords.length; i++) {
        var wordData = questionWords[i];
        if (wordData.word !== "" && wordData.shouldBeBlanked) {
            if (didAddOneToList) {
                output += separator;
            }
            output += wordData.word;
            didAddOneToList = true;
        }
    }
    return output;
}

// https://stackoverflow.com/a/1026087/3938401
function lowercaseFirstLetter(string) {
    if (string.length == 0) {
        return "";
    }
    return string.charAt(0).toLowerCase() + string.slice(1);
}

function removeSpaces(str) {
    return str.replace(/ /g,'');
}