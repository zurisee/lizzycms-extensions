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
function mylog(txt)
{
	console.log(txt);
	if (!$('body').hasClass('debug')) {
		return;
	}
	var $log = $('#log');
	if (!$log.length) {
		$('body').append("<div id='log'></div>");
		$log = $('#log');
	}
	txt = String(txt).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	$log.append('<p>'+timeStamp()+'&nbsp;&nbsp;'+txt+'</p>').animate({ scrollTop: $log.prop("scrollHeight")}, 100);
} // mylog



//--------------------------------------------------------------
function htmlEntities(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
