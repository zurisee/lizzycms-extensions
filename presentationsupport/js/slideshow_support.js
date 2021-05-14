// SlideShow Support for Lizzy
//


var currentSlide = 1;

$( document ).ready(function() {

    if (!$('.slideshow-support').length) {
        return;
    }

    mylog('activating slideshow-support', false);
    presi = new LzyPresentationSupport();
    presi.init();
});


function LzyPresentationSupport() {

    this.init = function() {
        this.initPresiElements();
        this.initEventHandlers();
        this.resizeSection();
        this.revealPresentationElement();
    };


    this.initPresiElements = function(){
        let $sections = $('section');
        if (!$sections.length) {
            return; // no sections found
        }

        let i = 1;
        let elInx = 1;
        $sections.addClass('lzy-withheld-section');
        $sections.each(function() {
            let $section = $( this );
            $section.addClass('lzy-presentation-section lzy-presentation-element lzy-presentation-element-' + elInx++);

            let $withheldElements = $('.withhold, .lzy-withhold', $section);
            $withheldElements.addClass('lzy-presentation-element');
            if ( $withheldElements.length ) {
                $withheldElements.each(function () {
                    $( this ).addClass('lzy-presentation-element-' + elInx++);
                });
            }
            i++;
        });
    }; // initPresiElements



    this.initEventHandlers = function(){
        let parent = this;
        $('.lzy-prev-page-link a').click(function(e) {
            e.preventDefault();
            parent.revealPresentationElement(-1 );
        });

        $('.lzy-next-page-link a').click(function(e) {
            e.preventDefault();
            parent.revealPresentationElement(1 );
        });

        this.initKeyHandlers();

    }; // initEventHandlers



    this.initKeyHandlers = function() {
        let parent = this;
        let $ugLightbox = $('.ug-lightbox');
        let $activeElement = $(document.activeElement);
        $( 'body' ).keydown( function (e) {

            // Exceptions, where arrow keys should NOT switch page:
            if ($activeElement.closest('form').length ||	// Focus within form field
                $activeElement.closest('input').length ||	// Focus within input field
                $activeElement.closest('textarea').length ||	// Focus within textarea field
                // $('.inhibitPageSwitch').length  ||				    // class .inhibitPageSwitch found
                ($ugLightbox.length && ($ugLightbox.css('display') !== 'none'))) {	// special case: ug-album in full screen mode

                mylog('in form: ' + $activeElement.closest('form').length);
                mylog('in input: ' + $activeElement.closest('input').length);
                mylog('in textarea: ' + $activeElement.closest('textarea').length);
                mylog('ug-lightbox: ' + $ugLightbox.length + ' - ' + $ugLightbox.css('display'));
                return document.defaultAction;
            }

            let keycode = e.which;
            if ((keycode === 39) || (keycode === 34)) {	// right or pgdown
                return parent.revealPresentationElement( 1 );

            } else if ((keycode === 37) || (keycode === 33)) {	// left or pgup
                return parent.revealPresentationElement( -1 );

            } else if ((keycode === 190) || (keycode === 110)) {	// . (dot)
                $('body').toggleClass('screen-off');
            }

        });
    }; // initKeyHandlers



    this.revealPresentationElement = function( which ) {
        if (typeof which === 'undefined') {
            if( window.location.hash ) {
                currentSlide = parseInt( window.location.hash.substr(1) );

            } else if ($('#lzy-slide-elem').length) {
                let reqSlideElem = $('#lzy-slide-elem').text();
                if (reqSlideElem === '-1') {
                    currentSlide = which = $('.lzy-presentation-element').length;
                } else {
                    currentSlide = parseInt( reqSlideElem );
                }

            } else {
                currentSlide = 1;
            }

        } else if (which > 0) {         // next
            currentSlide++;
            if (!$('.lzy-presentation-element-' + currentSlide).length) {
                let url = $('.lzy-next-page-link a').attr('href');
                mylog('go to next page: ' + url);
                lzyReload('', url);
                // lzyReload('fresh', url);
                return false;
            }

        } else if (which < 0) {         // previous
            currentSlide--;
            if (currentSlide <= 0) {
                let url = $('.lzy-prev-page-link a').attr('href');
                mylog('go to prev page: ' + url);
                lzyReloadPost( url, { lzySlideElem: -1 } );
                // lzyReload('', url);
                // lzyReload('fresh', url);
                return false;
            }
        } else {
            return true;
        }

        // Follow feature unfinished:
        // if (typeof presBackend !== 'undefined') {
        //     execAjax({ el: currentSlide }, '',  null, presBackend);
        //     // execAjax(null, 'el=' + currentSlide,  null, presBackend);
        // }

        // set all sections and elements to inactive:
        $('section').addClass('lzy-withheld-section').removeClass('lzy-active-section');
        $('.withhold, .lzy-revealed').addClass('lzy-withhold').removeClass('lzy-revealed withhold');

        let $newActiveElement = null;
        for (let elem = 1; elem <= currentSlide; elem++) {
            $newActiveElement = $('.lzy-presentation-element-' + elem);
            if ( !$newActiveElement.length ) {
                mylog('Error...');
                return true;
            }

            mylog('opening element ' + elem, false);
            // now reveal active element:
            if (!$newActiveElement.hasClass('lzy-presentation-section')) {
                $newActiveElement.removeClass('withhold lzy-withhold').addClass('lzy-revealed');
            }
        }
        // reveal active section:
        let $activeSection = $newActiveElement.closest('.lzy-presentation-section');
        if (typeof $activeSection !== 'undefined') {
            $activeSection.addClass('lzy-active-section').removeClass('lzy-withheld-section');
        }

        // inject current slide-element-number into page->pagenumber:
        $('.pagenumber .lzy-pg-no').text('.' + currentSlide);

        return false;
    }; // revealPresentationElement



    this.resizeSection = function() {
        if ($('.lzy-slideshow-no-size-adjusting').length ) {
            $('.lzy-slide-hide-initially').removeClass('lzy-slide-hide-initially');
            return;
        }

        // handle fixed size provided in attribute data-font-size:
        let $fixSize = $('[data-font-size]');
        if ($fixSize.length ) {
            let fixSize = $fixSize.attr('data-font-size');
            let $section = $fixSize.closest('section');
            if ($section.length) {
                $section.css({ fontSize: fixSize });
                $('.lzy-slide-hide-initially').removeClass('lzy-slide-hide-initially');
                return;
            }
        }

        let fSize = 10;
        let maxFSize = 28;
        let bodyH = $('body').height();

        let $main = $( 'main' );
        let $sections = $('section');

        let mainH = $main.innerHeight();
        let vPagePaddingPx = $main.padding();
        let vPageMarginPx = $main.margin();
        let vPadding = vPagePaddingPx.top + vPagePaddingPx.bottom + vPageMarginPx.top + vPageMarginPx.bottom;
        let mainHavail = mainH - 5;
        mylog('bodyH: ' + bodyH + ' mainH: ' + mainH + ' vPad: ' + vPadding + ' => ' + mainHavail, false);

        $sections.hide();    // hide while determening height of each section
        let debug = $('body').hasClass('debug');
        if ( debug ) {
            $('.slideshow-support').addClass('lzy-slide-visible').removeClass('lzy-slide-hide-initially');
        }
        const corr = 37; // -> padding top+bottom ToDo: compute that
        const m = 1.2;   // 

        mainHavail -= corr;
        $sections.each(function () {
            let $section = $( this );
            $section.show();
            if ( debug ) {
                $section.css('opacity', 0.4);
            }
            let contentH = $section.height() + corr;
            let f = mainHavail / contentH;
            let diff = mainHavail - contentH;
            let fontSize = $section.css('font-size');
            fSize = parseInt(fontSize.substr(0, fontSize.length - 2));

            mylog('====== mainH: ' + mainHavail, false);
            for (let i = 0; i < 10; i++) {
                diff = mainHavail - contentH;
                fSize = fSize * f;
                mylog(i + ': diff: ' + Math.trunc( diff ) + ' fontSize: ' + Number(fSize.toFixed(1)) + ' sectionH: ' + Math.trunc( contentH ), false);
                if (Math.abs(diff) < 3) {
                    break;
                }
                $section.css({fontSize: fSize.toString() + 'pt'});
                contentH = $section.height();
                f = mainHavail / contentH;
            }
            if (fSize > maxFSize) {
                $section.css({fontSize: maxFSize.toString() + 'pt'});
            }
            $section.hide();
            if ( debug ) {
                $section.css('opacity', '');
            }
        });
        $sections.show();
        $('.slideshow-support').addClass('lzy-slide-visible').removeClass('lzy-slide-hide-initially');

    }; // resizeSection

} // LzyPresentationSupport
