/*
**    Touch-Detector
*/

(function ( $ ) {
	var isTouchDevice = 'ontouchstart' in document.documentElement;
//isTouchDevice = true; // for debugging
//	mylog('touch-detector.js: isTouchDevice = '+isTouchDevice);

	var w = window,
		d = document,
		e = d.documentElement,
		g = d.getElementsByTagName('body')[0],
		w = w.innerWidth || e.clientWidth || g.clientWidth,
		h = w.innerHeight|| e.clientHeight|| g.clientHeight;
//w = 300; // for debugging
//	mylog('W: '+w+' H: '+h);

	if (isTouchDevice) {
		$('body').addClass('touch');
		if (w < 400) {
			$('body').addClass('small-screen');
			mylog('small-screen: true');
//			$('form .field-wrapper').addClass('js-float-label-wrapper');
		}
	}
}( jQuery ));
