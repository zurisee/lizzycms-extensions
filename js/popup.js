/*
 *  Tooltips extension for popup-overlay library
 */

// $.fn.popup.defaults.pagecontainer = '.page';
// var tooltipPos = 'bottom';

var arrowSize = 10;

$('[data-lzy-tooltip-from]').each(function() {
    var $tooltipShowAt = $(this);
    var tooltipTextFrom = '#' + $(this).attr('data-lzy-tooltip-from');

    $( tooltipTextFrom ).popup({
        type: 'tooltip',
        focuselement: false,
        keepfocus: false,
        // autoopen: true, // for dev only
        tooltipanchor: $( this ),
        onopen: function() {
            placeTooltip( $(this), $tooltipShowAt);
        },
    });
    $( $tooltipShowAt ).click(function() {  // click & focus to open permanently
        $( this ).toggleClass('lzy-tooltip-open');
        if ( $( this ).hasClass('lzy-tooltip-open') ) {
            setTimeout( function() {$( tooltipTextFrom ).popup('show'); }, 50);
        } else {
            $( tooltipTextFrom ).popup('hide');
        }
    })
});

// set up event handlers:
$('[data-lzy-tooltip-from]').on({
    'mouseenter focus': function(e) {
        if ($(this).hasClass('lzy-tooltip-open')) { return; }
        $( '#' + $(this).attr('data-lzy-tooltip-from') ).popup('show');
    },
    'mouseleave blur': function(e) {
        if ($(this).hasClass('lzy-tooltip-open') && (e.type != 'blur')) { return; }
        $( '#' + $(this).attr('data-lzy-tooltip-from') ).popup('hide');
    },
});


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
            tooltipPos =  (bottomGap > (y1 + 2*arrowSize)) ? 'bottom' : 'top';

        } else { 				// vertically not possible -> go horizontally
            tooltipPos =  (rightGap > (x1 + 2*arrowSize)) ? 'right' : 'left';
        }

    } else if (tP == 'h') {		// try horizontally:
        if ((topGap > vertReq) && (bottomGap > vertReq)) {		// sufficient space vertically?
            tooltipPos =  (rightGap > (x1 + 2*arrowSize)) ? 'right' : 'left';

        } else {				// horizontally not possible -> go vertically
            tooltipPos =  (bottomGap > (y1 + 2*arrowSize)) ? 'bottom' : 'top';
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
    var x = (x1/2 + x2/2 + arrowSize) * xDir;
    var y = (y1/2 + y2/2 + arrowSize) * yDir;
    $tooltipTextFrom.css('transform', 'translateX(' + x + 'px) translateY(' + y + 'px)');
    $('.lzy-tooltip-arrow').removeClass('lzy-tooltip-arrow-left lzy-tooltip-arrow-right lzy-tooltip-arrow-top lzy-tooltip-arrow-bottom ')
        .addClass('lzy-tooltip-arrow-' + ttCls);
} // placeTooltip
