// JS script that mines names off wikipedia HTML
// regex of \*\s*\[\[(.+\|)?(.+)\]\] also works on the source (edit page for Wikipedia page)
// license for names: https://en.wikipedia.org/wiki/Wikipedia:Text_of_Creative_Commons_Attribution-ShareAlike_3.0_Unported_License
var items = []; 
$('.mw-parser-output ul li').toArray().forEach(function(x) { 
    var text = x.innerText;
    if (!text.includes('ISBN') && !text.includes('0-')) {
        if (text.includes('(or')) {
            text = text.substring(0, text.indexOf('(or'));
        }
        if (text.includes('(variant')) {
            text = text.substring(0, text.indexOf('(variant'));
        }
        if (text.includes('(Greek')) {
            text = text.substring(0, text.indexOf('(Greek'));
        }
        if (text.includes('(Hos')) {
            text = text.substring(0, text.indexOf('(Hos'));
        }
        if (text.includes('(bibl')) {
            text = text.substring(0, text.indexOf('(bibl'));
        }
        if (text.includes('(name')) {
            text = text.substring(0, text.indexOf('(name'));
        }
        if (text.includes('[citation')) {
            text = text.substring(0, text.indexOf('[citation'));
        }
        if (text.includes(',')) {
            text = text.substring(0, text.indexOf(','));
        }
        if (text !== 'Lockyer' && !text.includes('\t') && !text.includes('\n')) {
            items.push(text); 
        }
    }
}); 
console.log(JSON.stringify(items));
console.log(items.length); 