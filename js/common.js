
function pageString(startPage, endPage) {
    if (typeof endPage !== 'undefined' && endPage != null && endPage != '' && endPage > startPage) {
        return 'pp. ' + startPage + '-' + endPage;
    } else if (typeof startPage !== 'undefined' && startPage !== null && startPage !== '') {
        return 'p. ' + startPage;
    }
    return '';
}

function commentaryVolumeString(volume, startPage, endPage) {
    var str = 'Volume ' + volume;
    var pageStr = pageString(startPage, endPage);
    if (pageStr !== '') {
        str += ', ' + pageStr;
    }
    return str;
}

function isBibleQuestion(type) {
    return type === 'bible-qna' || type === 'bible-qna-fill';
}

function isCommentaryQuestion(type) {
    return type === 'commentary-qna' || type === 'commentary-qna-fill';
}

function isFillInQuestion(type) {
    return type.indexOf('-fill') !== -1;
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
        if (wordData.before !== '') {
            output += wordData.before;
        }
        if (wordData.word !== '') {
            if (wordData.shouldBeBlanked) {
                if (shouldBoldWords) {
                    var html = '<strong>' + wordData.word + '</strong>';
                    output += html;
                }
                else if (shouldAvoidInputFields) {
                    output += '________';
                }
                else {
                    var html = '<span><input class="fill-in-blank-input" type="text" value="" data-autosize-input=\'{ "space": 4 }\'></input></span>';
                    output += html;
                }
            }
            else {
                output += wordData.word;
            }
        }
        if (wordData.after !== '') {
            output += wordData.after;
        }
        if (i != questionWords.length - 1 && wordData.after !== '...'  && wordData.after !== '…') {
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
        return '';
    }
    return string.charAt(0).toLowerCase() + string.slice(1);
}

function uppercaseFirstLetter(string) {
    if (string.length == 0) {
        return '';
    }
    return string.charAt(0).toUpperCase() + string.slice(1);
}

function removeSpaces(str) {
    return str.replace(/ /g,'');
}

// https://stackoverflow.com/a/2998822/3938401
function padZeros(num, size) {
    var s = '000000000' + num;
    return s.substr(s.length-size);
}

// https://stackoverflow.com/a/4579228/3938401
function strStartsWith(haystack, needle) {
    return haystack.lastIndexOf(needle, 0) === 0;
}

function shouldLowercaseOutput(output, names) {
    // get first word (I was too lazy to write this myself): https://stackoverflow.com/a/46999443/3938401
    var firstWord = output.replace(/ .*/,'');
    return !strStartsWith(output, 'T or') && 
           !(strStartsWith(output, 'God') && !strStartsWith(output, 'Gods') && !strStartsWith(output, 'gods')) &&
           !strStartsWith(output, 'Christ') && 
           !strStartsWith(output, 'Jesus') && 
           !strStartsWith(output, 'Jehová') && 
           !names.includes(firstWord);
}

function updateSelect2(selector) {
    $(selector).trigger('change');
}

function showElement(element) {
    element.classList.remove('d-none');
}

function hideElement(element) {
    element.classList.add('d-none');
}
/*
$(document).ready(function() {
    $(".dropdown-button").dropdown({gutter: 0, hover: false, belowOrigin: true});
    $(".dropdown-button").parent().find('li').click(function(e) {
        var id = e.currentTarget.id; // the actual language ID is the last element
        var languageID = id.split('-')[3];
        
    });
});*/