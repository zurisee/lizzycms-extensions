

(function ( $ ) {
    if (!$('#lzy').length) {
        alert("Warning: '#lzy'-Id missing within this page \n-> Lizzy's nav() objects not working.");
    }

    // initialize accordion:
    $('#lzy .lzy-nav-accordion .has-children > div').attr('aria-expanded', 'false');     // initialize 'aria-expanded'

    $('#lzy .lzy-nav-accordion.lzy-nav-opened li.has-children').each(function() {
        toggleAccordion($(this), true);
    });

    // initialize slide-down:
    $('#lzy .lzy-nav-slidedown .has-children > div').attr('aria-expanded', 'false');     // initialize 'aria-expanded'

    $('#lzy .lzy-nav-slidedown.lzy-nav-opened li.has-children').each(function() {
        toggleAccordion($(this), true);
    });
    $('#lzy .lzy-nav-slidedown:not(.lzy-nav-opened) li.has-children').each(function() {
        toggleAccordion($(this), false);
    });

    setHightOnHiddenElements();

    $('#lzy .lzy-nav-accordion a, #lzy .lzy-nav-slidedown a').click(function(e) {
        e.stopPropagation();
    });

    // click on accordion icon -> operate accordion:
    $('#lzy  li.has-children > a').keydown( function (e) {
        if (e.which == 32) {
            e.stopPropagation();
            e.preventDefault();
            toggleAccordion($(this).parent());
        }
    });




    // === .lzy-nav-open-current ==============================
    $('#lzy .lzy-nav-open-current .active').each(function() {
        toggleAccordion($(this), true);
    });

    // activate transitions, if requested:
    if ($('.lzy-nav-accordion.lzy-nav-showTransition').length) {
        setTimeout(function () {
            $('.lzy-nav-accordion').removeClass('lzy-nav-showTransition').addClass('lzy-nav-showTransition-active');
        }, 1000);
    }



    // open / close upon click on icon:
    $('#lzy .lzy-nav-accordion .has-children, #lzy .lzy-nav-slidedown .has-children').dblclick(function() {        // double click
        e.stopPropagation();
        e.preventDefault();
        $( 'div', this ).attr('aria-expanded', 'true');
        $( '.has-children', this ).addClass('open');
        $( this ).addClass('open');
        $( 'a', this ).attr('tabindex', '0');             // make focusable
    });

    $('#lzy .lzy-nav-accordion .has-children, #lzy .lzy-nav-slidedown .has-children').click(function(e) {          // click
        e.stopPropagation();
        toggleAccordion($(this));
    });




    function toggleAccordion($parentLi, newState) {
        var $nextDiv = $( '> div', $parentLi );
        var $nextDivs = $( 'div', $parentLi );
        if (typeof newState == 'undefined') {
            var expanded = ($nextDiv.attr('aria-expanded') === 'true');
        } else {
            var expanded = !newState;
        }
        if (expanded) {
            $nextDivs.attr({'aria-expanded': 'false', 'aria-hidden':'true' });        // next div
            $parentLi.removeClass('open').css('cursor', 's-resize');    // parent li
            $( 'li', $parentLi ).removeClass('open');            // all li below parent li
            $('a', $nextDivs).attr('tabindex', '-1');            // make un-focusable
        } else {
            $nextDiv.attr({'aria-expanded': 'true', 'aria-hidden':'false'});        // next div
            $parentLi.addClass('open').css('cursor', 'n-resize');       // parent li
            $('> div > ol > li > a', $parentLi).attr('tabindex', '0');             // make focusable
        }

    }


    if ($('body.small-screen').length) {
        operateMobileMenuPane( false );
    }


    // menu button in mobile mode:
    $('#lzy-nav-menu-icon').click(function(e) {
        e.stopPropagation();
        operateMobileMenuPane();
    });

    // click on dimmed overlay
    $('#lzy-dimm-overlay').click(function() {
        operateMobileMenuPane();
    });


}( jQuery ));



function operateMobileMenuPane( newState ) {
    var $nav = $( '.lzy-nav-wrapper' );
    var expanded = ($nav.attr('aria-expanded') === 'true');
    if (typeof newState != 'undefined') {
        expanded = !newState;
    }
    if (expanded) {
        $nav.attr('aria-expanded', 'false');
        $('body').removeClass('lzy-nav-mobile-open');
        $('header .lzy-nav a').attr('tabindex', '-1');            // make un-focusable

    } else {
        $nav.attr('aria-expanded', 'true');
        $('body').addClass('lzy-nav-mobile-open');
        $('header .lzy-nav > ol > li > a').attr('tabindex', '0');            // make un-focusable
    }
}


setTimeout(setHightOnHiddenElements, 500);
var resizeT = null;

function setHightOnHiddenElements() {
    $('#lzy .lzy-nav-accordion .has-children, #lzy .lzy-nav-slidedown .has-children').each(function() {
        var h = $('>div>ol', this).height() + 20;                // set height to optimize animation
        $('>div>ol', this).css('margin-top', '-'+h+'px');
    });
}

