

(function ( $ ) {
    if (!$('#lzy').length) {
        alert("Warning: '#lzy'-Id missing within this page \n-> Lizzy's nav() objects not working.");
    }

    if ($('.lzy-nav-accordion, .lzy-nav-slidedown, .lzy-nav-top-horizontal').length) {
        setHightOnHiddenElements();
    }


    // menu button in mobile mode:
    $('#lzy-nav-menu-icon').click(function(e) {
        e.stopPropagation();
        operateMobileMenuPane();
    });

    // open/close sub-nav:
    $('#lzy .lzy-nav .has-children > label').click(function(e) {        // double click
        var $parentLi = $(this).parent();
        toggleAccordion($parentLi);
    });
    $('#lzy .lzy-nav .has-children > label').dblclick(function(e) {        // double click
        e.stopPropagation();
        var $parentLi = $(this).parent();
        toggleAccordion($parentLi, true, true);
    });


    // let hover effect continue while mouse is over arrow (i.e. label):
    $('.lzy-nav label').hover(
        function() {
            $( '> a', $(this).parent() ).addClass( "hover" );
        }, function() {
            $( '> a', $(this).parent() ).removeClass( "hover" );
        }
    );

    // prepare small-screen nav: expand
    $('.small-screen .primary-nav .lzy-nav > ol > li').each(function() {
        toggleAccordion($( this ), true, true);
    });


    // activate animations now (thus, avoiding flicker)
    $('.lzy-nav-animated').each(function() {
        $( this ).removeClass('lzy-nav-animated').addClass('lzy-nav-animation-active');
    });

    if ($('body.small-screen').length) {
        operateMobileMenuPane( false );
    }

}( jQuery ));



function toggleAccordion($parentLi, newState, deep) {
    var $nextDiv = $( '> div', $parentLi );
    var $nextDivs = $( 'div', $parentLi );
    if (typeof newState == 'undefined') {
        // var expanded = ($nextDiv.attr('aria-expanded') === 'true');
        var expanded = ($('input', $parentLi).is(':checked'));
    } else {
        var expanded = !newState;
    }
    if (expanded) {
        $nextDivs.attr({'aria-expanded': 'false', 'aria-hidden':'true' });        // next div
        $parentLi.removeClass('open').css('cursor', 's-resize');    // parent li
        $( 'li', $parentLi ).removeClass('open');            // all li below parent li
        $('a', $nextDivs).attr('tabindex', '-1');            // make un-focusable
        $('input', $nextDivs).prop('checked', false);            // un-check checkbox

    } else {
        if ((typeof deep != 'undefined') && deep) {
            $nextDivs.attr({'aria-expanded': 'true', 'aria-hidden':'false' });        // next div
            $parentLi.addClass('open').css('cursor', 'n-resize');    // parent li
            $( 'li.has-children', $parentLi ).addClass('open');            // all li below parent li
            $('a', $parentLi).attr('tabindex', '0');            // make un-focusable
            $('input', $parentLi).prop('checked', true);            // un-check checkbox
        } else {
            $nextDiv.attr({'aria-expanded': 'true', 'aria-hidden':'false'});        // next div
            $parentLi.addClass('open').css('cursor', 'n-resize');       // parent li
            $('> div > ol > li > a', $parentLi).attr('tabindex', '0');             // make focusable
        }
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
        $('.primary-nav .lzy-nav a').attr('tabindex', '-1');            // make un-focusable

    } else {
        $nav.attr('aria-expanded', 'true');
        $('body').addClass('lzy-nav-mobile-open');
        var $primaryNav = $('.primary-nav .lzy-nav');
        $('> ol > li > a', $primaryNav).attr('tabindex', '0');            // make un-focusable
        $primaryNav.addClass('lzy-nav-collapsed lzy-nav-open-current');
    }
}




function setHightOnHiddenElements() {
    $('#lzy .lzy-nav-accordion .has-children, #lzy .lzy-nav-top-horizontal .has-children, #lzy .lzy-nav-collapsed .has-children').each(function() {
        var h = $('>div>ol', this).height() + 20;                // set height to optimize animation
        $('>div>ol', this).css('margin-top', '-'+h+'px');
    });
}

