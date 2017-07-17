<?php
    require_once(dirname(__FILE__)."/init.php");
    $sections = load_home_sections($pdo);
?>

<?php include(dirname(__FILE__)."/header.php"); ?>

<div id="user-links">
    <p>Hello, <span contenteditable="true" class="browser-default fill-in-blank-input"></span>! <span class="only-bottom-border fill-in-blank-input" contenteditable="true">testificate</span> </p>
</div>
<p></p>
<div id="user-links">
    <p>Hello, <span><input class="browser-default fill-in-blank-input" type="text" value="" placeholder="Autosize" data-autosize-input='{ "space": 4 }' /></span>!</p>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        // denying line breaks (enter key): https://stackoverflow.com/a/428139/3938401
        $("span").keypress(function(e) { return e.which != 13; } );
    });
    // https://github.com/MartinF/jQuery.Autosize.Input/blob/master/jquery.autosize.input.min.js TODO: refactor
    // https://stackoverflow.com/a/17098915/3938401
    var Plugins;(function(n){var t=function(){function n(n){typeof n=="undefined"&&(n=30);this.space=n}return n}(),i;n.AutosizeInputOptions=t;i=function(){function n(t,i){var r=this;this._input=$(t);this._options=$.extend({},n.getDefaultOptions(),i);this._mirror=$('<span style="position:absolute; top:-999px; left:0; white-space:pre;"/>');$.each(["fontFamily","fontSize","fontWeight","fontStyle","letterSpacing","textTransform","wordSpacing","textIndent"],function(n,t){r._mirror[0].style[t]=r._input.css(t)});$("body").append(this._mirror);this._input.on("keydown keyup input propertychange change",function(){r.update()});(function(){r.update()})()}return n.prototype.getOptions=function(){return this._options},n.prototype.update=function(){var n=this._input.val()||"",t;n!==this._mirror.text()&&(this._mirror.text(n),t=this._mirror.width()+this._options.space,this._input.width(t))},n.getDefaultOptions=function(){return this._defaultOptions},n.getInstanceKey=function(){return"autosizeInputInstance"},n._defaultOptions=new t,n}();n.AutosizeInput=i,function(t){var i="autosize-input",r=["text","password","search","url","tel","email","number"];t.fn.autosizeInput=function(u){return this.each(function(){if(this.tagName=="INPUT"&&t.inArray(this.type,r)>-1){var f=t(this);f.data(n.AutosizeInput.getInstanceKey())||(u==undefined&&(u=f.data(i)),f.data(n.AutosizeInput.getInstanceKey(),new n.AutosizeInput(this,u)))}})};t(function(){t("input[data-"+i+"]").autosizeInput()})}(jQuery)})(Plugins||(Plugins={}))

</script>

<?php include(dirname(__FILE__)."/footer.php") ?>