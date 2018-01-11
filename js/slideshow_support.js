// SlideShow Support for Lizzy

$( document ).ready(function() {

    var prevLink = $('.prevPageLink a').attr('href');
    var nextLink = $('.nextPageLink a').attr('href');
    var slideSupportActive = $('.slideshow-support').length;
    if (slideSupportActive) {
        console.log('slideshow-support activated');
    }
    if ($('body').hasClass('reveal-all')) {
        revealWithheldElements( true );
    }

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
                if (slideSupportActive &&  hideRevealedElements() ) {
                    console.log( 'withheld' );
                    return false;
                }
                window.location = prevLink;
			}
			if ((nextLink != 'undefined') && nextLink && (ev.type == 'swipeleft')) {
                if (slideSupportActive &&  revealWithheldElements() ) {
                    console.log( 'revealed' );
                    return false;
                }
                window.location = nextLink;
			}
		});
	}




    // Clicks on forward/backward links:
    $('.prevPageLink a').click(function(e) {
        console.log('prevLink: '+prevLink);
        e.preventDefault();
        if ( hideRevealedElements()  ) {
            console.log( 'revealed element hidden' );
        } else {
            window.location = prevLink + '?revealall';
        }
        return false;
    });
    $('.nextPageLink a').click(function(e) {
        console.log('nextLink: '+nextLink);
        if ( revealWithheldElements() ) {
            console.log( 'withheld element revealed' );
            e.preventDefault();
            return false;
        }
        return true;
    });





    // Key handling:
    $( 'body' ).keydown( function (e) {
        var keycode = e.which;

        // Exceptions, where arrow keys should NOT switch page:
        if ($( document.activeElement ).closest('form').length ||	// Focus within form field
            $( document.activeElement ).closest('input').length ||	// Focus within input field
            // $('.inhibitPageSwitch').length  ||				    // class .inhibitPageSwitch found
            ($('.ug-lightbox').length && ($('.ug-lightbox').css('display') != 'none'))) {	        // special case: ug-album in full screen mode

            console.log('in form: '+$( document.activeElement ).closest('form').length);
            console.log('in input: '+$( document.activeElement ).closest('input').length);
            console.log('ug-lightbox: '+$('.ug-lightbox').length + ' - ' + $('.ug-lightbox').css('display'));
            return document.defaultAction;
        }

        // slideshow-support:
        if (slideSupportActive && slideSupport(e) == false) {
            return false;
        }

        // Standard arrow key handling:
        if ((keycode == 37) || (keycode == 33)) {	// left or pgup
            console.log('prevLink: '+prevLink);
            e.preventDefault();
            window.location = prevLink + '?revealall';
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





    function slideSupport(e)
    {
        var keycode = e.which;

        // slideshow-support:
        if ((keycode == 39) || (keycode == 34)) {	// right or pgdown
            if ( revealWithheldElements() ) {
                console.log( 'withheld element revealed' );
                e.preventDefault();
                return false;
            }
        }
        if ((keycode == 37) || (keycode == 33)) {	// left or pgup
            if ( hideRevealedElements()  ) {
                console.log( 'revealed element hidden' );
                e.preventDefault();
                return false;
            }
        }
        if ((keycode == 190) || (keycode == 110)) {	// . (dot)
            $('body').toggleClass('screen-off');
        }

        if ($( document.activeElement ).closest('form').length ||	// Focus within form
            $('.inhibitPageSwitch').length ) {					// class .inhibitPageSwitch found
            //console.log( 'Focus within form -> skip custom key action' );
            return document.defaultAction;
        }
        if (typeof _ugZoomedMode != 'undefined') {
            console.log('keydown - _ugZoomedMode: '+ _ugZoomedMode);
            if (_ugZoomedMode) { // skip, if unite gallery is in zoom mode
                return false;
                // return;
            }
        }
        return true;
    } // slideSupport





    function revealWithheldElements( all )  // forward -> move to next page or reveal elements
    {   // as long as there are elements '.withheld', reveal them one by one
        // allow switching to next page if there are no '.withheld' left
        if (typeof all !== 'undefined') {
            $('.withheld').removeClass('withheld').addClass('revealed');
            return true;
        }
        var $this = $('.withheld').first();
        if ( $this.length > 0) {
            $this.removeClass('withheld').addClass('revealed');
            return true;
        } else {
            // $('.inhibitPageSwitch').removeClass('inhibitPageSwitch');
            return false;
        }
    } // revealWithheldElements



    

    function hideRevealedElements()         // move to previous page
    {   // if there are '.revealed' elements, cover them all (at once, not one by one)
        // allow switchen to previous page if there are no '.revealed' elements
        console.log( 'hiding' );
        if (($('.revealed').length > 0) && ($('.reveal-all').length == 0)) {
            $('.revealed').addClass('withheld').removeClass('revealed');
            return true;
        } else {
            // $('.inhibitPageSwitch').removeClass('inhibitPageSwitch');
            return false;
        }
    } // hideRevealedElements

});

