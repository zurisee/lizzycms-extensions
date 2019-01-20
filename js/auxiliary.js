//--------------------------------------------------------------
// handle screen-size and resize
(function ( $ ) {
    if ($(window).width() < screenSizeBreakpoint) {
        $('body').addClass('small-screen');
    } else {
        $('body').addClass('large-screen');
    }

    $(window).resize(function(){
        var w = $(this).width();
        if (w < screenSizeBreakpoint) {
            $('body').addClass('small-screen').removeClass('large-screen');
        } else {
            $('body').removeClass('small-screen').addClass('large-screen');
        }
    });
}( jQuery ));



//--------------------------------------------------------------
function showMessage( txt )
{
    $('.MsgBox').remove();
    $('body').prepend( '<div class="MsgBox"><p>' + txt + '</p></div>' );
}



//--------------------------------------------------------------
function mylog(txt)
{
	console.log(txt);

	if ($('body').hasClass('debug')) {
        var $log = $('#log');
        if (!$log.length) {
            $('body').append("<div id='log-placeholder'></div><div id='log'></div>");
            $log = $('#log');
        }
        text = String(txt).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        $log.append('<p>'+timeStamp()+'&nbsp;&nbsp;'+text+'</p>').animate({ scrollTop: $log.prop("scrollHeight")}, 100);
	}

    if ($('body').hasClass('logging-to-server')) {
        serverLog(txt)
    }
} // mylog



//--------------------------------------------------------------
function serverLog(text)
{
    if (text) {
        $.post( systemPath+'_ajax_server.php?log', { text: text } );
    }
} // serverLog




//--------------------------------------------------------------
function lzyReload()
{
    console.log('initiating page reload');
    // location.reload(true);
    var call = window.location.pathname.replace(/\?.*/, '');
    window.location.assign(call);
}



//--------------------------------------------------------------
function timeStamp()
{
	var now = new Date();
	var time = [ now.getHours(), now.getMinutes(), now.getSeconds() ];
	for ( var i = 1; i < 3; i++ ) {
		if ( time[i] < 10 ) {
			time[i] = "0" + time[i];
		}
	}
	return time.join(":");
} // timeStamp




//--------------------------------------------------------------
function htmlEntities(str)
{
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}


/*
// Make Overlay closable by ESC key:
$( document ).ready(function() {
	if ($('.overlay').length) {
		$('body').keydown(function (e) {
			var keycode = e.which;
			if (keycode == 27) {
				$('.overlay').hide();
			}
		});
	}
});
*/


//--------------------------------------------------------------
// plug-in to get specified element selected
jQuery.fn.selText = function() {
    this.find('input').each(function() {
        if($(this).prev().length == 0 || !$(this).prev().hasClass('p_copy')) {
            $('<p class="p_copy" style="position: absolute; z-index: -1;"></p>').insertBefore($(this));
        }
        $(this).prev().html($(this).val());
    });
    var doc = document;
    var element = this[0];
    if (doc.body.createTextRange) {
        var range = document.body.createTextRange();
        range.moveToElementText(element);
        range.select();
    } else if (window.getSelection) {
        var selection = window.getSelection();
        var range = document.createRange();
        range.selectNodeContents(element);
        selection.removeAllRanges();
        selection.addRange(range);
    }
}
