var largeScreenClasses = '';


(function ( $ ) {
    if (!$('#lzy').length) {
        alert("Warning: '#lzy'-Id missing within this page \n-> Lizzy's nav() objects not working.");
    }
    largeScreenClasses = $('.lzy-primary-nav .lzy-nav').attr('class');

    var isSmallScreen = ($(window).width() < screenSizeBreakpoint);
    adaptMainMenuToScreenSize(isSmallScreen);

    $(window).resize(function(){
        var w = $(this).width();
        var isSmallScreen = (w < screenSizeBreakpoint);
        adaptMainMenuToScreenSize(isSmallScreen);
        setHightOnHiddenElements( false );
    });


    // if ($('.lzy-nav-accordion, .lzy-nav-slidedown, .lzy-nav-top-horizontal').length) {
    if ($('.lzy-nav-collapse, .lzy-nav-top-horizontal').length) {
        // setHightOnHiddenElements(); //??? Android-bug?
    }

    $('.lzy-nav-top-horizontal .lzy-lvl1.lzy-has-children').each(function() {
        // console.log($(this));
        // $('div a, div label', $( this )).attr('tabindex', '-1');             // make un-focusable
    });

    // $('.touchevents .lzy-nav .lzy-has-children > a').click(function (e) {
    $('.touchevents .lzy-has-children a').click(function (e) {
        e.stopPropagation();
        var $parentLi = $(this).parent();
        toggleAccordion($parentLi, true);
        return false;
    });

    // menu button in mobile mode:
    $('#lzy-nav-menu-icon').click(function(e) {
        e.stopPropagation();
        operateMobileMenuPane();
    });


    // mouse:
    $('.lzy-has-children > * > .lzy-nav-arrow').dblclick(function(e) {        // double click -> open all
        var $parentLi = $(this).closest('.lzy-has-children');
        toggleAccordion($parentLi, true, true);
        // toggleAccordion($parentLi, true, true, true);
        e.stopPropagation();
        return false;
    });
    $('.lzy-has-children > label').click(function(e) {        // click
        console.log( 'click label' );
        var $parentLi = $(this).closest('.lzy-has-children');
        toggleAccordion($parentLi);
        // toggleAccordion($parentLi, true);
        e.stopPropagation();
        return false;
    });
    $('.lzy-has-children > a > .lzy-nav-arrow').click(function(e) {        // click
        console.log( 'click arrow' );
        var $parentLi = $(this).closest('.lzy-has-children');
        toggleAccordion($parentLi);
        // toggleAccordion($parentLi, true);
        e.stopPropagation();
        return false;
    });

    // hover:
   $('.lzy-nav-hoveropen .lzy-has-children').hover(
        function() {    // mouseover
            // mylog('mouseover');
            var $this = $(this);
            if ($('body').hasClass('touch')) {  // no-touch only
                return; // ??? -> better to remove
            }
            if ($this.closest('.lzy-nav').hasClass('lzy-nav-top-horizontal')) { // top-nav:
                if ($this.hasClass('lzy-lvl1')) {
                    $this.addClass('lzy-hover');
                    openAccordion($this, true);
                    // $('a', $this).attr('tabindex', '0');             // make focusable
                }
            } else {        // side-nav or sitemap
                $this.addClass( 'lzy-hover' );
                openAccordion($this);
            }
            // $('a', $this).attr({'aria-expanded': 'true'});

        },
       function() {     // mouseout
           // mylog('mouseout');
           var $this = $(this);
           if ($('body').hasClass('touch')) {  // no-touch only
               return;
           }
           if ($this.closest('.lzy-nav').hasClass('lzy-nav-top-horizontal')) {  // top-nav
               if ($this.hasClass('lzy-lvl1')) {
                   $this.removeClass('lzy-hover');
                   closeAccordion($this, true);
                   // $('a', $this).attr('tabindex', '-1');             // make un-focusable
               }
           } else {        // side-nav or sitemap
               $this.removeClass( 'lzy-hover' );
               closeAccordion($this);
           }
           // $('a', $this).attr({'aria-expanded': 'false'});
        }
   );


    // activate animations now (avoiding flicker)
    $('.lzy-nav-animated').each(function() {
        $( this ).removeClass('lzy-nav-animated').addClass('lzy-nav-animation-active');
    });

    if ($('body').hasClass('lzy-small-screen')) {
        operateMobileMenuPane( false );
    }

    setupKeyboardEvents();
    initTopNav();
}( jQuery ));





function handleAccordion( elem ) {
    if ($( 'body' ).hasClass('touch')) {
        var $parentLi = $(elem).parent();
        if ($parentLi.hasClass('lzy-lvl1')) {
            toggleAccordion($parentLi, true);
            return false;
        }
    }
} // handleAccordion





function initTopNav() {
    // top-nav:
    $('.lzy-large-screen .lzy-primary-nav .lzy-nav-top-horizontal .lzy-has-children').each(function() {
        var $elem = $( this );
        var $nextDivs = $( 'div', $elem );
        $elem.removeClass('lzy-open lzy-hover');
        $('li', $elem ).removeClass('lzy-open');       // close all li below parent li
        $nextDivs.attr({'aria-hidden':'true' });        // make all sub-elements hidden
        $('a', $nextDivs).attr({'tabindex': '-1'}); // make sub-menus un-focusable
        // $('a', $nextDivs).attr({'aria-expanded': 'false', 'tabindex': '-1'}); // make sub-menus un-focusable
    });
} // initTopNav




function toggleAccordion($parentLi, deep, newState) {
    if (typeof newState === 'undefined') {
        var expanded = $parentLi.hasClass('lzy-open');
    } else {
        var expanded = !newState;
    }

    if ($parentLi.closest('.lzy-nav').hasClass('lzy-nav-top-horizontal')) {
        closeAllAccordions($parentLi);
    } else if (expanded) {
        closeAccordion($parentLi, true);
    }
    if (!expanded) { // -> collapse
        openAccordion($parentLi, true);
        return true;
    }
    return false;
} // toggleAccordion



function openAccordion($parentLi, deep) {
    $parentLi.addClass('lzy-open');
    $('> a', $parentLi).attr({'aria-expanded': 'true'});  // make focusable
    if (deep === true) {
        // var $nextDivs = $( 'div', $parentLi );
        $( 'div', $parentLi ).attr({'aria-hidden': 'false'});        // next div
        // $parentLi.addClass('lzy-open');       // parent li
        $('.lzy-has-children', $parentLi).addClass('lzy-open');       // parent li
        $('a', $parentLi).attr({'tabindex': ''});  // set aria-expanded and make focusable
        // $('a', $parentLi).attr({'tabindex': '', 'aria-expanded': 'true'});  // set aria-expanded and make focusable

    } else {
        // var $nextDiv = $( '> div', $parentLi );
        $( '> div', $parentLi ).attr({'aria-hidden': 'false'});
        // $parentLi.addClass('lzy-open');
        $('> a', $parentLi).attr({'tabindex': ''});  // make focusable
        // $('> a', $parentLi).attr({'tabindex': '', 'aria-expanded': 'true'});  // make focusable
        // $('> div > ol > li > a', $parentLi).attr({'tabindex': '', 'aria-expanded': 'true'});  // set aria-expanded and make focusable
    }
} // openAccordion




function closeAccordion($parentLi, deep) {
    var $nextDiv = $( '> div', $parentLi );
    if (typeof deep === true) {
        $nextDiv = $( 'div', $parentLi );
    }
    $('> a', $parentLi).attr({'aria-expanded': 'false'});  // make focusable
    $('a', $parentLi).attr({'tabindex': ''});  // make focusable
    // $('a', $parentLi).attr({'tabindex': '', 'aria-expanded': 'false'});  // make focusable
    $nextDiv.attr({'aria-hidden':'true'});        // next div
    // $nextDiv.attr({'aria-expanded': 'false', 'aria-hidden':'true'});        // next div
    $parentLi.removeClass('lzy-open');       // parent li
    // $('> input', $parentLi).prop('checked', false);            // check checkbox
    $('li > a', $parentLi).attr('tabindex', '-1');             // make un-focusable
    // $('li > a, li > label', $parentLi).attr('tabindex', '-1');             // make un-focusable
} // closeAccordion




// all accordions includes siblings:
function closeAllAccordions($parentLi) {
    var $nav = $parentLi.closest('.lzy-nav');
    $('> a', $parentLi).attr({'aria-expanded': 'false'});  // make focusable
    $('a', $parentLi).attr({'tabindex': ''});  // make focusable
    // $('a', $parentLi).attr({'tabindex': '', 'aria-expanded': 'false'});  // make focusable
    $('.lzy-has-children', $nav).each(function() {
        var $elem = $( this );
        var $nextDivs = $( 'div', $elem );
        $elem.removeClass('lzy-open lzy-hover');
        $nextDivs.attr({'aria-hidden':'true' });        // next div
        // $nextDivs.attr({'aria-expanded': 'false', 'aria-hidden':'true' });        // next div
        $('a', $nextDivs).attr('tabindex', '-1');            // make un-focusable
        // $('a, label', $nextDivs).attr('tabindex', '-1');            // make un-focusable
        $('li', $elem ).removeClass('lzy-open');            // all li below parent li
        // $('input', $nav).prop('checked', false);            // un-check checkbox
    });
} // closeAllAccordions




function adaptMainMenuToScreenSize( smallScreen ) {
    if (smallScreen) {
        $('.lzy-primary-nav .lzy-nav')
            .removeClass('lzy-nav-top-horizontal lzy-nav-hover lzy-nav-colored lzy-nav-dark-theme')
            .addClass('lzy-nav-collapsed lzy-nav-open-current');
        $('.lzy-primary-nav .active').each(function() {
            console.log( $(this) );
            var $this = $(this);
            openAccordion( $this, false );
        });
    } else {
        $('.lzy-primary-nav .lzy-nav').attr('class', largeScreenClasses);
        $('.lzy-primary-nav input').prop('checked', false);
        $('.lzy-primary-nav .lzy-has-children').removeClass('lzy-open');
        $('body').removeClass('lzy-nav-mobile-open');
    }
}




function operateMobileMenuPane( newState ) {
    var $nav = $( '.lzy-nav-wrapper' );
    var expanded = ($nav.attr('aria-expanded') === 'true');
    if (typeof newState != 'undefined') {
        expanded = !newState;
    }
    if (expanded) {
        $nav.attr('aria-expanded', 'false');
        $('body').removeClass('lzy-nav-mobile-open');
        $('.lzy-primary-nav .lzy-nav a').attr('tabindex', '-1');            // make un-focusable

    } else {
        $nav.attr('aria-expanded', 'true');
        $('body').addClass('lzy-nav-mobile-open');
        var $primaryNav = $('.lzy-primary-nav .lzy-nav');
        $('> ol > li > a', $primaryNav).attr('tabindex', '0');            // make un-focusable
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
    $('.lzy-nav a, .lzy-nav label').keydown(function (event) {
        var keyCode = event.keyCode;
        event.stopPropagation();
        var $this = $(this);

        if (keyCode == 39) {            // right arrow
            event.preventDefault();
            toggleAccordion($this.parent(),false, true);
            // toggleAccordion($this.parent(), false,false, true);

        } else if (keyCode == 37) {    // left arrow
            event.preventDefault();
            var expanded = $this.parent().hasClass('lzy-open');
            if (expanded) { // if open -> close
                toggleAccordion($this.parent(),false, false);
                // toggleAccordion($this.parent(), false,false, false);
            } else {    // if already closed -> jump to parent element
                $('> a', $this.parent().parent().closest('li')).focus();
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
            toggleAccordion($this.parent());
            // toggleAccordion($this.parent(), false);
        }
    });
} // setupKeyboardEvents


