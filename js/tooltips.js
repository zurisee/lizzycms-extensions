/*
 *  Tooltips extension for popup-overlay library
 */


var defaultArrowSize = 10;
var timeout = false;

// Setup:
$('[data-lzy-tooltip-from]').each(function() {
    var $tooltipShowAt = $(this); //$( anchorId );
    var anchorId = $tooltipShowAt.attr('id');
    if (typeof anchorId == 'undefined') {
        anchorId = 'lzy-tooltip-anchor0' + Math.floor(Math.random()*1000 );
        $( this ).attr('id', anchorId);
    }
    anchorId = '#' + anchorId;
    console.log('setting up ' + anchorId);
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
    if (!$tooltipShowAt.hasClass('lzy-tooltip-anchor')) {
        $tooltipShowAt.addClass('lzy-tooltip-anchor');
    }
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
    'mouseenter focus': function() {
        var $tooltipShowAt = $(this);
        if ($tooltipShowAt.hasClass('lzy-tooltip-open')) { return; }
        var sel = ':not(' + $tooltipShowAt.attr('data-lzy-tooltip-from') + ').lzy-tooltip-content';
        if (timeout ) { //
            clearTimeout(timeout);
        }
        $(sel).popup('hide');
        var $tooltipTextFrom = $( $tooltipShowAt.attr('data-lzy-tooltip-from') );
        $tooltipShowAt.attr('aria-expanded', 'true');
        $tooltipTextFrom.popup({
            tooltipanchor: $tooltipShowAt,
            autoopen: true,
            onopen: function() { placeTooltip( $tooltipTextFrom, $tooltipShowAt); },
        });
        // close tooltips on bg-tap, unless sticky
        if (!$tooltipShowAt.hasClass('lzy-tooltip-sticky')) {
            $('body.touch').on('touchstart', function () {
                $($tooltipShowAt.attr('data-lzy-tooltip-from')).popup('hide');
            });
        }
    },
    'mouseleave blur': function(e) {
        var $this = $(this);
        var sticky = $this.hasClass('lzy-tooltip-sticky'); // if sticky, don't close on blur:
        if ((sticky || $this.hasClass('lzy-tooltip-open')) && (e.type == 'mouseleave')) { return; }
        $this.attr('aria-expanded', 'false');
        if (e.type == 'mouseleave') {
            timeout = setTimeout(function () {
                $($this.attr('data-lzy-tooltip-from')).popup('hide');
            }, 500);
        } else {
            $($this.attr('data-lzy-tooltip-from')).popup('hide');
        }
    }
}); // on



var dim = {};

function placeTooltip( $tooltipTextFrom, $tooltipShowAt) {
    var xDir = 0, yDir = 0, ttCls = '';
    var popupW = $tooltipTextFrom.outerWidth();
    var anchorW = $tooltipShowAt.innerWidth();
    var popupH = $tooltipTextFrom.outerHeight();
    var anchorH = $tooltipShowAt.innerHeight();

    var thisArrowSize = defaultArrowSize;
    var arrowSizeStr = $tooltipShowAt.attr('data-lzy-tooltip-arrow-size');
    if (typeof arrowSizeStr !== 'undefined') {
        thisArrowSize = parseInt(arrowSizeStr);
    }

    var anchorDim = $tooltipShowAt[0].getBoundingClientRect();
    var popupDim = $tooltipTextFrom[0].getBoundingClientRect();

    // how much bigger popup is than anchor:
    dim.dx = (popupDim.width - anchorDim.width) * 0.5;
    dim.dy = (popupDim.height - anchorDim.height) * 0.5;

    // available space around anchor:
    dim.at = anchorDim.top;
    dim.ab = $(window).height() - anchorDim.bottom;
    dim.al = anchorDim.left;
    dim.ar = $(window).width() - anchorDim.right;

    // size of popup:
    dim.ph = popupDim.height + thisArrowSize;
    dim.pw = popupDim.width + thisArrowSize;

    // evaluate data and class attributes to find prefered position:
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

    // determine best possible position:
    var tP = (typeof tooltipPos == 'string') ? tooltipPos.substring(0, 1) : '';	// just determine by first letter (l,r,t,b,h,v)
    tooltipPos = determineBestPosition(tP);

    // activate position:
    switch (tooltipPos) {
        case 'top': yDir = -1; ttCls = 'bottom'; break;
        case 'bottom': yDir = 1; ttCls = 'top'; break;
        case 'left': xDir = -1; ttCls = 'right'; break;
        case 'right': xDir = 1; ttCls = 'left'; break;
    }

    // move tooltip in selected direction away from anchor:
    var moveX = ((popupW + anchorW) * 0.5 + thisArrowSize) * xDir;
    var moveY = (popupH/2 + anchorH * 0.5 + thisArrowSize) * yDir;
    $tooltipTextFrom.css('transform', 'translateX(' + moveX + 'px) translateY(' + moveY + 'px)');
    $('.lzy-tooltip-arrow').removeClass('lzy-tooltip-arrow-left lzy-tooltip-arrow-right lzy-tooltip-arrow-top lzy-tooltip-arrow-bottom ')
        .addClass('lzy-tooltip-arrow-' + ttCls);
} // placeTooltip



function determineBestPosition(tP) {
    var tooltipPos = '';
    if ((tP == '') || (tP == 'v')) {	// case: no or vertical preference
        tooltipPos = bestPosition('t > b > r > l');

    } else if (tP == 'h') {		// case: horizontal preference
        tooltipPos = bestPosition('r > l > t > b');

    } else { // case: specific preference
        switch (tP) {
            case 't':
                tooltipPos = bestPosition('t > b > r > l');
                break;
            case 'b':
                tooltipPos = bestPosition('b > t > r > l');
                break;
            case 'l':
                tooltipPos = bestPosition('l > r > t > b');
                break;
            case 'r':
                tooltipPos = bestPosition('r > l > t > b');
                break;
        }
    }
    return tooltipPos;
} // determinePosition



function bestPosition(preferences) {
    var prefArray = preferences.split('>');
    var result = 'right'; // = last resort if nothing fits
    for (var i in prefArray) {
        var pos = prefArray[i].trim();
        var res = false;
        switch (pos) {
            case 't': res = (dim.at > dim.ph) && (dim.ar > dim.dx) && (dim.al > dim.dx); break;
            case 'b': res = (dim.ab > dim.ph) && (dim.ar > dim.dx) && (dim.al > dim.dx); break;
            case 'r': res = (dim.ar > dim.pw) && (dim.at > dim.dy) && (dim.ab > dim.dy); break;
            case 'l': res = (dim.al > dim.pw) && (dim.at > dim.dy) && (dim.ab > dim.dy); break;
        }
        if (res) {
            var names = {t: 'top', b: 'bottom', l: 'left', r: 'right'};
            result = names[pos];
            break;
        }
    }
    return result;
} // sufficientSpace

