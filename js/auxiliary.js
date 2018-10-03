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
function mylog(txt)
{
	console.log(txt);

	if ($('body').hasClass('debug')) {
        var $log = $('#log');
        if (!$log.length) {
            $('body').append("<div id='log'></div>");
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
    location.reload();
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

