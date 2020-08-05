/*---------------
 * jQuery iToggle Plugin by Engage Interactive
 * Examples and documentation at: http://labs.engageinteractive.co.uk/itoggle/
 * Copyright (c) 2009 Engage Interactive
 * Version: 1.0 (10-JUN-2009)
 * Dual licensed under the MIT and GPL licenses:
 * http://www.opensource.org/licenses/mit-license.php
 * http://www.gnu.org/licenses/gpl.html
 * Version: 1.7 (30-May-2012) by pshiryaev
 * Requires: jQuery v1.7 or later
---------------*/
!function(i){i.fn.iToggle=function(e){function a(e,a){1==e?"radio"==o.type?i("label[for="+a+"]").addClass("ilabel_radio"):i("label[for="+a+"]").addClass("ilabel"):i("label[for="+a+"]").remove()}function l(e,a){o.onClick.call(e),h=e.innerHeight(),t=e.prop("for"),e.hasClass("iTon")?(o.onClickOff.call(e),e.animate({backgroundPositionX:"100%",backgroundPositionY:"-"+h+"px"},o.speed,o.easing,function(){e.removeClass("iTon").addClass("iToff"),clickEnabled=!0,o.onSlide.call(this),o.onSlideOff.call(this)}),i("input#"+t).removeAttr("checked"),i("input#"+t).trigger("change")):(o.onClickOn.call(e),e.animate({backgroundPositionX:"0%",backgroundPositionY:"-"+h+"px"},o.speed,o.easing,function(){e.removeClass("iToff").addClass("iTon"),clickEnabled=!0,o.onSlide.call(this),o.onSlideOn.call(this)}),i("input#"+t).prop("checked","checked"),i("input#"+t).trigger("change")),1==a&&(name=i("#"+t).prop("name"),l(e.siblings("label[for]")))}clickEnabled=!0;var n={type:"checkbox",keepLabel:!0,easing:!1,speed:200,onClick:function(){},onClickOn:function(){},onClickOff:function(){},onSlide:function(){},onSlideOn:function(){},onSlideOff:function(){}},o=i.extend({},n,e);this.each(function(){var e=i(this);if(e.is("input")){var l=e.attr("id");if(a(o.keepLabel,l),e.attr("checked")){n=i('<label class="itoggle" for="'+l+'"><span></span></label>');e.addClass("iT_checkbox").before(n);s=i(n).innerHeight();i(n).css({"background-position-x":"0%","background-position-y":"-"+s+"px"}),e.prev("label").addClass("iTon")}else{var n=i('<label class="itoggle" for="'+l+'"><span></span></label>');e.addClass("iT_checkbox").before(n);var s=i(n).innerHeight();i(n).css({"background-position-x":"100%","background-position-y":"-"+s+"px"}),e.prev("label").addClass("iToff")}}else e.children("input:"+o.type).each(function(){var e=i(this).attr("id");if(a(o.keepLabel,e),i(this).attr("checked")){l=i('<label class="itoggle" for="'+e+'"><span></span></label>');i(this).addClass("iT_checkbox").before(l);n=i(l).innerHeight();i(l).css({"background-position-x":"0%","background-position-y":"-"+n+"px"}),i(this).prev("label").addClass("iTon")}else{var l=i('<label class="itoggle" for="'+e+'"><span></span></label>');i(this).addClass("iT_checkbox").before(l);var n=i(l).innerHeight();i(l).css({"background-position-x":"100%","background-position-y":"-"+n+"px"}),i(this).prev("label").addClass("iToff")}"radio"==o.type&&i(this).prev("label").addClass("iT_radio")})}),i("label.itoggle").click(function(){return 1==clickEnabled&&(clickEnabled=!1,i(this).hasClass("iT_radio")?i(this).hasClass("iTon")?clickEnabled=!0:l(i(this),!0):l(i(this))),!1}),i("label.ilabel").click(function(){return 1==clickEnabled&&(clickEnabled=!1,l(i(this).next("label.itoggle"))),!1})}}(jQuery);