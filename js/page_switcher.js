// Page Switcher for Lizzy

(function ( $ ) {

	if ($('body').hasClass('touch')) {
		console.log('swipe detection activated');

		$('.page').hammer().bind("swipeleft swiperight", function(ev) {
		    var overflow = $(ev.gesture.target).css('overflow');
		    if ((overflow == 'auto') || (overflow == 'scroll')) {
                console.log('page switching suppressed: was over scrolling element');
		        return;
            }
			var prev = $('.prevPageLink a').attr('href');
			if ((prev != 'undefined') && prev && (ev.type == 'swiperight')) {
				window.location = prev;
			}
			var next = $('.nextPageLink a').attr('href');
			if ((next != 'undefined') && next && (ev.type == 'swipeleft')) {
				window.location = next;
			}
		});
	}
    $( 'body' ).keydown( function (e) {
        var keycode = e.which;
        if ($( document.activeElement ).closest('form').length ||	// Focus within form field
            $( document.activeElement ).closest('input').length ||	// Focus within input field
            $('.pageSwitchInhibited').length  ||				    // class .pageSwitchInhibited found
            ($('.ug-lightbox').length && ($('.ug-lightbox').css('display') != 'none'))) {	        // special case: ug-album in full screen mode
            return document.defaultAction;
        }
        var prev = $('.prevPageLink a').attr('href');

        if ((keycode == 37) || (keycode == 33)) {	// left or pgup
            console.log('prev: '+prev);
            e.preventDefault();
            window.location = prev;
        }
        var next = $('.nextPageLink a').attr('href');
        if ((keycode == 39) || (keycode == 34)) {	// right or pgdown
            console.log('next: '+next);
            e.preventDefault();
            window.location = next;
        }
        if (keycode == 115) {
            if (typeof simplemde == 'undefined') {	// F4 -> start editing mode
                e.preventDefault();
                window.location = '?edit';
            }
        }
        return document.defaultAction;
    });

}( jQuery ));



