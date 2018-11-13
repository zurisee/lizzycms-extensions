/*
 *  Popup.js
 *
*/


(function ( $ ) {

    $.fn.lzyPopup = function( options ) {
        var id = createPopupContainer( options );
        populatePopupContainer(id, options);
        setSpecialOptions(id, options);
        setupTrigger(id, options);
        initEsc();
    };

}( jQuery ));


function createPopupContainer(options) {
    var id = (options.id) ? options.id : 'lzy-popup' + options.index;

    if ($('#' + id).length == 0) {
        $('#lzy-popup-template').after('<div id="' + id + '" class="lzy-popup-widget" style="display:none;"></div>');
        id = '#' + id;

        $(id).html($('#lzy-popup-template').html());   // initialize or reset popup

        var $popup = $(id + ' .lzy-popup-wrapper');

        if (options.class) {
            $popup.addClass(options.class);
        }
        if (options.width) {
            $popup.css('width', options.width);
        }
        if (options.height) {
            $popup.css('min-height', options.height);
        }

    } else {
        id = '#' + id;
        var $popup = $(id + ' .lzy-popup-wrapper');
    }

    $('.lzy-popup-close-button', $popup).click(function() {
        $( this ).closest('.lzy-popup-widget').hide();
    });

    if (options.lightbox && (options.triggerEvent != 'hover')) {   // lightbox not compatible with hover
        $(id).addClass('lzy-lightbox');
    }
    return id;
}


function populatePopupContainer(id, options)
{
    var $popup = $(id + ' .lzy-popup-wrapper');
    var text = '';
    if (options.text) {     // text provided in options
        text = options.text;
    }
    if (options.contentFrom) {   // text to be retrieved
        text = text +  $( options.contentFrom ).html();
        $( options.contentFrom ).remove();
    }

    $('.lzy-popup-body', $popup).html(text);
    $('.lzy-popup-header', $popup).html( options.header );
}



function setSpecialOptions(id, options)
{
    var $popup = $(id + ' .lzy-popup-wrapper');
    if (options.draggable) {
        $('.lzy-popup-header', $popup).panzoom({ $set: $popup }); // -> only draggable by header, not content
        // note: this is it avoid conflicts with forms in popup-body
    }

    $('.lzy-popup-close-button', $popup).click(function() {
        $( this ).closest('.lzy-popup-widget').hide();
    });
}




function openPopup(id, options) {
    positionPopup(id, options);
    if ((typeof options.delay != 'undefined') && (options.delay != '0')) {
        setTimeout(function() { $(id).show(); }, parseInt(options.delay));
    } else {
        $(id).show();
    }
}



function positionPopup(id, options) {
    var $popup = $(id + ' .lzy-popup-wrapper');
    var offsX = 0;
    var offsY = 0;
    if (options.anker) {
        $(id).css('position', 'absolute');

        var ankerOffset = $(options.anker).offset();
        offsX = ankerOffset.left + parseInt(options.offsetX);
        offsY = ankerOffset.top + parseInt(options.offsetY) - $(window).scrollTop();
        $popup.css({'left': offsX + 'px', 'top': offsY + 'px'});

    } else {
        $popup.css({'left': '50vw', 'top': '50vh', 'transform': 'translate(-50%, -50%)'});
    }
}




function setupTrigger(id, options) {
    if (!options.triggerEvent || options.triggerEvent == 'none') {
        // do nothing
    } else if (options.triggerSource) {
        switch (options.triggerEvent) {
            case 'double-click':
                $(options.triggerSource).dblclick(function () {
                    openPopup(id, options);
                });
                break;

            case 'hover':
                $( id ).addClass('lzy-tooltip');
                $(options.triggerSource).hover(
                    function () {
                        openPopup(id, options);
                    },
                    function () {
                        $(id).hide();
                    });
                break;

            case 'right-click':
                $(options.triggerSource).bind("contextmenu",function() {
                    openPopup(id, options);
                    return false;
                });
                break;

            case 'click':
                $(options.triggerSource).click(function (e) {
                    openPopup(id, options);
                });
                break;
            default:
                // do nothing
        }


    } else {
        openPopup(id, options);
    }

    if (options.closeOnBgClick) {
        $('.lzy-popup-background').click(function () {
            $('.lzy-popup-widget').hide();
        });
    }

/* attempt to fix Panzoom bug: focussable elements with frage no longer work */
    $('.lzy-popup-wrapper a').on('mousedown touchstart', function( e ) {
        e.stopImmediatePropagation();
    });
    $('.lzy-popup-wrapper button').on('mousedown touchstart', function( e ) {
        e.stopImmediatePropagation();
    });

    $('.lzy-popup-wrapper input').on('mousedown touchstart', function( e ) {    // -> not working
        e.stopImmediatePropagation();
    });
    $('.lzy-popup-wrapper textarea').on('mousedown touchstart', function( e ) {    // -> not working
        e.stopImmediatePropagation();
    });
}

function initEsc()
{
    $( 'body' ).keydown( function (e) {
        if (e.which == 27) {	// ESC
            $('.lzy-popup-widget').hide();
        }
    });
}


function popupClose()
{
    $('.lzy-popup-widget').hide();
}