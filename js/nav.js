var largeScreenClasses = '';


(function ( $ ) {
    if (!$('#lzy').length) {
        alert("Warning: '#lzy'-Id missing within this page \n-> Lizzy's nav() objects not working.");
    }
    largeScreenClasses = $('.primary-nav .lzy-nav').attr('class');

    var isSmallScreen = ($(window).width() < screenSizeBreakpoint);
    adaptMainMenuToScreenSize(isSmallScreen);

    $(window).resize(function(){
        var w = $(this).width();
        var isSmallScreen = (w < screenSizeBreakpoint);
        adaptMainMenuToScreenSize(isSmallScreen);
        setHightOnHiddenElements( false );
    });


    if ($('.lzy-nav-accordion, .lzy-nav-slidedown, .lzy-nav-top-horizontal').length) {
        setHightOnHiddenElements();
    }


    // menu button in mobile mode:
    $('#lzy-nav-menu-icon').click(function(e) {
        e.stopPropagation();
        operateMobileMenuPane();
    });

    // open/close sub-nav:
    $('#lzy .lzy-nav.lzy-nav-clicktoopen .lzy-has-children > a').click(function(e) {        // click
        // mylog('a');
        // console.log('click a');
        var $parentLi = $(this).closest('.lzy-has-children');
        toggleAccordion($parentLi, true);
    });
    $('#lzy .lzy-nav.lzy-nav-clicktoopen .lzy-has-children > label').keyup(function(e) {      // space bar
        if ((e.which === 13) || (e.which === 32)) {
            // console.log('space');
            // console.log(e.which);
            var $parentLi = $(e.target).closest('.lzy-has-children');
            toggleAccordion($parentLi, true);
        }
    });

    $('#lzy .lzy-nav .lzy-has-children > label').click(function(e) {        // click
        // mylog('label');
        var $parentLi = $(this).closest('.lzy-has-children');
        toggleAccordion($parentLi);
        return false;
    });

    // $('#lzy .lzy-nav .lzy-has-children > label').dblclick(function(e) {        // double click
    //     e.stopPropagation();
    //     var $parentLi = $(this).parent();
    //     toggleAccordion($parentLi, false, true, true);
    // });


    // let hover effect continue while mouse is over arrow (i.e. label):
   $('.lzy-nav-top-horizontal > ol > li.lzy-has-children').hover(
        function() {
            $(this).addClass( 'lzy-hover' );
        }, function() {
            $(this).removeClass( 'lzy-hover' );
        }
    );


    // prepare lzy-small-screen nav: expand
    $('.lzy-small-screen .primary-nav .lzy-nav > ol > li').each(function() {
        toggleAccordion($( this ), true, true);
    });


    // activate animations now (thus, avoiding flicker)
    $('.lzy-nav-animated').each(function() {
        $( this ).removeClass('lzy-nav-animated').addClass('lzy-nav-animation-active');
    });

    if ($('body.lzy-small-screen').length) {
        operateMobileMenuPane( false );
    }
    closeAllAccordions($('.primary-nav .lzy-has-children'));
    // setupKeyboardEvents();

}( jQuery ));


function adaptMainMenuToScreenSize( smallScreen ) {
    if (smallScreen) {
        $('.primary-nav .lzy-nav').removeClass('lzy-nav-top-horizontal lzy-nav-hover').addClass('lzy-nav-accordion');
        // console.log('nav set to smallScreen');
    } else {
        $('.primary-nav .lzy-nav').attr('class', largeScreenClasses);
        $('.primary-nav input').prop('checked', false);
        $('.primary-nav .lzy-has-children').removeClass('open');
        $('body').removeClass('lzy-nav-mobile-open');
        // console.log('nav set to largeScreen');
    }
}




function toggleAccordion($parentLi, manipCheckbox, newState, deep) {
    var $nextDiv = $( '> div', $parentLi );
    var $nextDivs = $( 'div', $parentLi );
    if (typeof newState == 'undefined') {
        var expanded = $parentLi.hasClass('open');
    } else {
        var expanded = !newState;
    }
    var manipCheckbox = (typeof manipCheckbox !== 'undefined');
    closeAllAccordions($parentLi);

    if (expanded) { // -> collapse
        return false;

    } else { // -> expand
        openAccordion($parentLi);
        return true;
    }
} // toggleAccordion



function openAccordion($parentLi) {
    var $nextDiv = $( '> div', $parentLi );
    $nextDiv.attr({'aria-expanded': 'true', 'aria-hidden':'false'});        // next div
    $parentLi.addClass('open');       // parent li
    $('> input', $parentLi).prop('checked', true);            // check checkbox
    $('> div > ol > li > a', $parentLi).attr('tabindex', '0');             // make focusable
} // closeAccordion





function closeAllAccordions($parentLi) {
    var $nav = $parentLi.closest('.lzy-nav');
    $('.lzy-has-children', $nav).each(function() {
        var $elem = $( this );
        var $nextDivs = $( 'div', $elem );
        $elem.removeClass('open lzy-hover');
        $nextDivs.attr({'aria-expanded': 'false', 'aria-hidden':'true' });        // next div
        $('a', $nextDivs).attr('tabindex', '-1');            // make un-focusable
        $('label', $nextDivs).attr('tabindex', '-1');            // make un-focusable
        $('li', $elem ).removeClass('open');            // all li below parent li
        $('input', $nav).prop('checked', false);            // un-check checkbox
        // $elem.removeClass( 'lzy-hover' );
    });
} // closeAccordion




function operateMobileMenuPane( newState ) {
    var $nav = $( '.lzy-nav-wrapper' );
    var expanded = ($nav.attr('aria-expanded') === 'true');
    if (typeof newState != 'undefined') {
        expanded = !newState;
    }
    if (expanded) {
        $nav.attr('aria-expanded', 'false');
        $('body').removeClass('lzy-nav-mobile-open');
        $('.primary-nav .lzy-nav a').attr('tabindex', '-1');            // make un-focusable

    } else {
        $nav.attr('aria-expanded', 'true');
        $('body').addClass('lzy-nav-mobile-open');
        var $primaryNav = $('.primary-nav .lzy-nav');
        $('> ol > li > a', $primaryNav).attr('tabindex', '0');            // make un-focusable
        $primaryNav.addClass('lzy-nav-collapsed lzy-nav-open-current');
    }
} // operateMobileMenuPane




function setHightOnHiddenElements( first ) {
    var doCalc = !(typeof first === 'undefined');
    $('#lzy .lzy-nav-accordion .lzy-has-children, #lzy .lzy-nav-top-horizontal .lzy-has-children, #lzy .lzy-nav-collapsed .lzy-has-children').each(function() {
        if (doCalc) {
            var h = '100vh';
        } else {
            var h = $('>div>ol', this).height() + 20 + 'px';                // set height to optimize animation
        }
        $('>div>ol', this).css('margin-top', '-'+h);
    });
} // setHightOnHiddenElements




function setupKeyboardEvents() {
    // supports: left/right, up/down, space and home
    $('.lzy-nav a').keydown(function (event) {
        var keyCode = event.keyCode;
        event.stopPropagation();

        if (keyCode == 39) {            // right arrow
            event.preventDefault();
            toggleAccordion($(this).parent(), true);

        } else if (keyCode == 37) {    // left arrow
            event.preventDefault();
            var expanded = $(this).parent().hasClass('open');
            if (expanded) { // if open -> close
                toggleAccordion($(this).parent(), false);
            } else {    // if already closed -> jump to parent element
                $('> a', $(this).parent().parent().closest('li')).focus();
            }

        } else if (keyCode == 36) {    // home
            event.preventDefault();
            $('.lzy-lvl1:first-child a').focus();

        } else if (keyCode == 38) {    // up
            event.preventDefault();
            $.tabPrev();

        } else if (keyCode == 40) {    // down
            event.preventDefault();
            $.tabNext();

        } else if (keyCode == 32) {    // space
            event.preventDefault();
            toggleAccordion($(this).parent());
        }
    });
} // setupKeyboardEvents


