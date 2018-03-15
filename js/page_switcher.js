// Page Switcher for Lizzy

$( document ).ready(function() {

    var prevLink = $('.prevPageLink a').attr('href');
    var nextLink = $('.nextPageLink a').attr('href');

    // Touch gesture handling:
	if ($('body').hasClass('touch')) {
		console.log('swipe detection activated');

		$('.page').hammer().bind("swipeleft swiperight", function(ev) {
		    var overflow = $(ev.gesture.target).css('overflow');
		    if ((overflow == 'auto') || (overflow == 'scroll')) {
                console.log('page switching suppressed: was over scrolling element');
		        return;
            }
			if ((prevLink != 'undefined') && prevLink && (ev.type == 'swiperight')) {
                window.location = prevLink;
			}
			if ((nextLink != 'undefined') && nextLink && (ev.type == 'swipeleft')) {
                window.location = nextLink;
			}
		});
	}

	// Key handling:
    $( 'body' ).keydown( function (e) {
        if (isProtectedTarget()) {
            return document.defaultAction;
        }

        var keycode = e.which;

        // Standard arrow key handling:
        if ((keycode == 37) || (keycode == 33)) {	// left or pgup
            console.log('prevLink: '+prevLink);
            e.preventDefault();
            window.location = prevLink;
            return false;
        }
        if ((keycode == 39) || (keycode == 34)) {	// right or pgdown
            console.log('nextLink: '+nextLink);
            e.preventDefault();
            window.location = nextLink;
            return false;
        }
        if (keycode == 115) {
            if (typeof simplemde == 'undefined') {	// F4 -> start editing mode
                e.preventDefault();
                window.location = '?edit';
                return false;
            }
        }
        return document.defaultAction;
    });
});




function isProtectedTarget()
{
    // Exceptions, where arrow keys should NOT switch page:
    if ($( document.activeElement ).closest('form').length ||	        // Focus within form field
        $( document.activeElement ).closest('input').length ||	        // Focus within input field
        $('.inhibitPageSwitch').length  ||				                // class .inhibitPageSwitch found
        ($('.ug-lightbox').length &&
            ($('.ug-lightbox').css('display') != 'none')) ||            // special case: ug-album in full screen mode
        $( document.activeElement ).closest('.lzy-panels-widget').length	// Focus within lzy-panels-widget field
    )
    {
        // console.log('skipping page-switcher');
        return true;
    }
    return false;
}