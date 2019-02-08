/*
 *  Tooltips extension for popup-overlay library
 */


var defaultArrowSize = 10;

// Setup:
$('[data-lzy-tooltip-from]').each(function() {
    var anchorId = '#' + $(this).attr('id');
    var $tooltipShowAt = $( anchorId );
    // console.log('setting up ' + anchorId);
    var tooltipTextFrom = $tooltipShowAt.attr('data-lzy-tooltip-from');
    var ch1 = tooltipTextFrom.substring(0,1);
    if ((ch1 != '#') && (ch1 != '.')) {
        tooltipTextFrom = '#' + tooltipTextFrom;
        $tooltipShowAt.attr('data-lzy-tooltip-from', tooltipTextFrom);
    }
    var $tooltipTextFrom = $(tooltipTextFrom);
    $tooltipTextFrom.addClass('lzy-tooltip-content').show(); // show() just in case it was hidden to prevent flicker
    if ($tooltipShowAt.hasClass('lzy-tooltip-arrow')) {
        $tooltipTextFrom.addClass('lzy-tooltip-arrow');
    }
    var catchFocus = ($tooltipShowAt.hasClass('lzy-tooltip-catch-focus'));

    // basic tooltip config:
    $tooltipTextFrom.popup({
        type: 'tooltip',
        focuselement: catchFocus,
        keepfocus: catchFocus,
    });
    $tooltipShowAt.attr('tabindex', '0').attr('aria-expanded', 'false');
});


// Event handlers:
$('[data-lzy-tooltip-from]').on({
    'click': function() {  // click to open permanently
        var $tooltipShowAt = $(this);
        var $tooltipTextFrom = $( $tooltipShowAt.attr('data-lzy-tooltip-from') );
        if ( !$tooltipShowAt.hasClass('lzy-tooltip-open') ) {
            $tooltipShowAt.addClass('lzy-tooltip-open');
            setTimeout( function() {
                $tooltipTextFrom.popup({
                    tooltipanchor: $tooltipShowAt,
                    autoopen: true,
                    onopen: function() { placeTooltip( $tooltipTextFrom, $tooltipShowAt);},
                });
                $tooltipShowAt.attr('aria-expanded', 'true');
            }, 50);
        } else {
            $tooltipTextFrom.popup('hide');
            $tooltipShowAt.attr('aria-expanded', 'false').removeClass('lzy-tooltip-open');
        }
    },
    'mouseenter focus': function(e) {
        var $tooltipShowAt = $(this);
        if ($tooltipShowAt.hasClass('lzy-tooltip-open')) { return; }

        var $tooltipTextFrom = $( $tooltipShowAt.attr('data-lzy-tooltip-from') );
        $tooltipShowAt.attr('aria-expanded', 'true');
        $tooltipTextFrom.popup({
            tooltipanchor: $tooltipShowAt,
            autoopen: true,
            onopen: function() { placeTooltip( $tooltipTextFrom, $tooltipShowAt);},
        });
    },
    'mouseleave blur': function(e) {
        var $this = $(this);
        if ($this.hasClass('lzy-tooltip-open') && (e.type == 'mouseleave')) { return; }
        $this.attr('aria-expanded', 'false');
        $( $this.attr('data-lzy-tooltip-from') ).popup('hide');
    }
}); // on




function placeTooltip( $tooltipTextFrom, $tooltipShowAt) {
    var xDir = 0, yDir = 0, ttCls = '';
    var x1 = $tooltipTextFrom.outerWidth();
    var x2 = $tooltipShowAt.innerWidth();
    var y1 = $tooltipTextFrom.outerHeight();
    var y2 = $tooltipShowAt.innerHeight();
    var w = $tooltipTextFrom.width();
    var h = $tooltipTextFrom.height();
    var winW = $(window).width();
    var winH = $(window).height();
    var leftGap   = $tooltipShowAt.offset().left;
    var rightGap  = winW - ($tooltipShowAt.offset().left + x2);
    var topGap    = $tooltipShowAt.offset().top;
    var bottomGap = winH - ($tooltipShowAt.offset().top + y2);
    var thisArrowSize = $tooltipShowAt.attr('data-lzy-tooltip-arrow-size');
    if (typeof thisArrowSize == 'undefined') {
        thisArrowSize = defaultArrowSize;
    }

    var tooltipPos = $tooltipShowAt.attr('data-lzy-tooltip-where');
    if (typeof tooltipPos == 'undefined') {
        if ($tooltipShowAt.hasClass('lzy-tooltip-right')) {
            tooltipPos = 'right';
        } else if ($tooltipShowAt.hasClass('lzy-tooltip-left')) {
            tooltipPos = 'left';
        } else if ($tooltipShowAt.hasClass('lzy-tooltip-top')) {
            tooltipPos = 'top';
        } else if ($tooltipShowAt.hasClass('lzy-tooltip-bottom')) {
            tooltipPos = 'bottom';
        }
    }
    var tP = (typeof tooltipPos == 'string') ? tooltipPos.substring(0, 1) : '';	// just determine by first letter (l,r,t,b,h,v)
    var horizReq = ((w - x2) / 2);
    var vertReq = ((h - y2) / 2);
    if ((tP == '') || (tP == 'v')) {	// no or vertical preference
        if ( (leftGap > horizReq) && (rightGap > horizReq) ) {	// sufficient space horizontally?
            tooltipPos =  (bottomGap > (y1 + 2*thisArrowSize)) ? 'bottom' : 'top';

        } else { 				// vertically not possible -> go horizontally
            tooltipPos =  (rightGap > (x1 + 2*thisArrowSize)) ? 'right' : 'left';
        }

    } else if (tP == 'h') {		// try horizontally:
        if ((topGap > vertReq) && (bottomGap > vertReq)) {		// sufficient space vertically?
            tooltipPos =  (rightGap > (x1 + 2*thisArrowSize)) ? 'right' : 'left';

        } else {				// horizontally not possible -> go vertically
            tooltipPos =  (bottomGap > (y1 + 2*thisArrowSize)) ? 'bottom' : 'top';
        }

    } else {
        switch (tP) {	// allow to use abreviations:
            case 't': tooltipPos = 'top'; break;
            case 'b': tooltipPos = 'bottom'; break;
            case 'l': tooltipPos = 'left'; break;
            case 'r': tooltipPos = 'right'; break;
        }
    }

    switch (tooltipPos) {
        case 'top': yDir = -1; ttCls = 'bottom'; break;
        case 'bottom': yDir = 1; ttCls = 'top'; break;
        case 'left': xDir = -1; ttCls = 'right'; break;
        case 'right': xDir = 1; ttCls = 'left'; break;
    }
    var x = (x1/2 + x2/2 + thisArrowSize) * xDir;
    var y = (y1/2 + y2/2 + thisArrowSize) * yDir;
    $tooltipTextFrom.css('transform', 'translateX(' + x + 'px) translateY(' + y + 'px)');
    $('.lzy-tooltip-arrow').removeClass('lzy-tooltip-arrow-left lzy-tooltip-arrow-right lzy-tooltip-arrow-top lzy-tooltip-arrow-bottom ')
        .addClass('lzy-tooltip-arrow-' + ttCls);
} // placeTooltip
