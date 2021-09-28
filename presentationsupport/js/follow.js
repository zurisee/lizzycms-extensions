/*
*	follow presentation
*/
// backend: ~sys/extensions/presentation-support/backend/_presentation-backend.php

// var beaconTimer = 0;
var updateTimer = 0;
var active_time = 3 * 60*1000; // stop updating after 10 minutes [ms]
var cycle_time =  2000; // [ms]	// -1 for debugging
var watchdog = false;
var watchdogDuration = 70000;

var stopTime = Date.now() + active_time;
var longPolling = true;
var lastUpdate = 0;

$(function() {
	// if ((long_poll !== 'undefined') && (long_poll !== '&poll=long')) {
	// 	// $('body').css('background', 'none');
	// 	// $('body img#bg').css('display', 'none');
	// 	// $('body #page').css('box-shadow', '0 0 5px 2px #aaa');
	// 	mylog('started in short poll mode');
	// 	longPolling = false;
	// }

	// $('h1').click(function() {	// for debugging
	// 	mylog('manual update client');
	// 	update(0);
	// });


    update(lastUpdate);
	// restartWatchdog();

	// $('#inactive').click(function() {
	// 	restart_updating();
	// });
}); // init



//--------------------------------------------------------------
function update(lastUpdate) {
	$.ajax({
		// url: "update.php?lu="+lu + long_poll,
		url: presBackend,
		type: 'POST',
		data: { last: lastUpdate },
		async: true,
		cache: false,
		success: function (data) {
			var updateTime = handleResponse(data);
			if (updateTime) {
				update(updateTime);
			}
		}
	});
} // update




function handleResponse(data) {
	var t = timeStamp();
	var tt = data.match(/\[(\d*)\]/);
	var updateTime = lastUpdate;
	if (tt !== null) {
		updateTime = parseInt(tt[1]);
	}
	mylog(t + ' update: ' + data);
	if (data.match(/^load:/)) {
		var m = data.match(/load: (.*)\s*\[\d+\]/);
		var url = m[1].trim();
		mylog('loading: "' + url + '"');
		lzyReload('?flw',url);
		return false;
	}
	return updateTime;
} // handleResponse



//--------------------------------------------------------------
function timeStamp() {
	var now = new Date();
	var date = [now.getFullYear(), now.getMonth() + 1, now.getDate() ];
	var time = [ now.getHours(), now.getMinutes(), now.getSeconds() ];
	for ( var i = 1; i < 3; i++ ) {
		if ( time[i] < 10 ) {
			time[i] = "0" + time[i];
		}
	}
	return date.join("-") + " " + time.join(":");
} // timeStamp
