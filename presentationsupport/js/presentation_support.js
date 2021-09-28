// SlideShow Support for Lizzy
//


var currentSlide = 1;

$( document ).ready(function() {

    if ($('.lzy-slideshow-support').length) {
        mylog('activating slideshow-support', false);
        presi = new LzyPresentationSupport();
        presi.init();
    }

});


function LzyPresentationSupport() {

    this.init = function() {
        this.initPresiElements();
        this.initEventHandlers();
        this.resizeSections();
        this.revealPresentationElement();
    }; // init



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

            $('.withhold', $section).addClass('lzy-withhold').removeClass('withhold');

            let $withheldElements = $('.lzy-withhold', $section);
            $withheldElements.addClass('lzy-presentation-element');
            if ( $withheldElements.length ) {
                $withheldElements.each(function () {
                    const $childrenWrapper = $( this );
                    $childrenWrapper.addClass('lzy-presentation-element-' + elInx++);
                    $withheldChildren = $('.withhold-bullets, .lzy-withhold-bullets', $childrenWrapper);
                    if ( $withheldChildren.length ) {
                        let $withholdChildren = $('li', $withheldChildren);
                        $withholdChildren.each(function () {
                            $( this ).addClass('lzy-withhold lzy-presentation-element lzy-presentation-element-' + elInx++);
                        });
                    }
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
        this.initMouseHandlers();
    }; // initEventHandlers



    this.initMouseHandlers = function() {
        // highlight mouse clicks:
        $('body').click(function( e ) {
            // skip if it's a next/prev link
            if ($(e.target).closest('.lzy-next-prev-page-links').length) {
                return;
            }
            $('#lzy-cursor-mark').show().css({ top: (e.pageY - 24), left: (e.pageX - 24) }).addClass('lzy-wobble-cursor');
            setTimeout(function() {
                $('#lzy-cursor-mark').removeClass('lzy-wobble-cursor').hide();
            }, 2000);
        });
    }; // initMouseHandlers



    this.initKeyHandlers = function() {
        let parent = this;
        let $ugLightbox = $('.ug-lightbox');
        let $activeElement = $(document.activeElement);
        $( 'body' ).keydown( function (e) {

            // Exceptions, where arrow keys should NOT switch page:
            if ($activeElement.closest('form').length ||	// Focus within form field
                $activeElement.closest('input').length ||	// Focus within input field
                $activeElement.closest('textarea').length ||	// Focus within textarea field
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
                $('body').toggleClass('lzy-screen-off');
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

        // inject current slide-element-number into pagenumber:
        $('.lzy-pagenumber .lzy-page-nr-postfix').text('.' + currentSlide);

        return false;
    }; // revealPresentationElement



    this.resizeSections = function() {
        let parent = this;
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
        let minFSize = 10;
        let maxFSize = 28;
        let bodyH = $('body').height();

        let $main = $( 'main' );
        let $sections = $('.lzy-presentation-section');

        let mainH = $main.innerHeight();
        let vPagePaddingPx = $main.padding();
        let vPageMarginPx = $main.margin();
        let vPadding = vPagePaddingPx.top + vPagePaddingPx.bottom + vPageMarginPx.top + vPageMarginPx.bottom;
        let mainHavail = mainH - 5;
        mylog('bodyH: ' + bodyH + ' mainH: ' + mainH + ' vPad: ' + vPadding + ' => ' + mainHavail, false);

        $sections.hide();    // hide while determening height of each section
        let debug = $('body').hasClass('debug');
        const $slideshowSupport = $('.lzy-slideshow-support');
        if ( debug ) {
            $slideshowSupport.addClass('lzy-slide-visible').removeClass('lzy-slide-hide-initially');
        }
        const corr = 37; // -> padding top+bottom ToDo: compute that

        mainHavail -= corr;
        $sections.each(function () {
            let $section = $( this );
            $section.show();
            if ( debug ) {
                $section.css('opacity', 0.4);
            }

            //data-section-font-size -> fixed font-size for current section:
            let $fixSize = $('[data-section-font-size]', $section);
            if ($fixSize.length ) {
                let fixSize = $fixSize.attr('data-section-font-size');
                $section.css({ fontSize: fixSize });

            } else {
                // check 'data-section-min-fontsize', return pt value
                minFSize = parent.getMinMaxFontSize('min', minFSize, $section);

                // check 'data-section-max-fontsize', return pt value
                maxFSize = parent.getMinMaxFontSize('max', maxFSize, $section);

                let contentH = $section.height() + corr;
                let f = mainHavail / contentH;
                let diff = mainHavail - contentH;
                fSize = minFSize;

                mylog('====== mainH: ' + mainHavail, false);
                for (let i = 0; i < 10; i++) {
                    diff = mainHavail - contentH;
                    fSize = fSize * f;
                    mylog(i + ': diff: ' + Math.trunc(diff) + ' fontSize: ' + Number(fSize.toFixed(1)) +
                        ' sectionH: ' + Math.trunc(contentH), false);
                    if (Math.abs(diff) < 3) {
                        break;
                    }
                    $section.css({fontSize: fSize.toString() + 'pt'});
                    contentH = $section.height();
                    f = mainHavail / contentH;
                }
                if (fSize > maxFSize) {
                    $section.css({fontSize: maxFSize.toString() + 'pt'});
                } else if (fSize < minFSize) {
                    $section.css({fontSize: minFSize.toString() + 'pt'});
                }
            }
            $section.hide();
            if ( debug ) {
                $section.css('opacity', '');
            }
        });
        $sections.show();
        $slideshowSupport.addClass('lzy-slide-visible').removeClass('lzy-slide-hide-initially');

    }; // resizeSections



    this.getMinMaxFontSize = function( which, minmaxFSize, $section ) {
        const $minmaxFSize = $('[data-section-'+which+'-fontsize]', $section);
        if ($minmaxFSize.length) {
            const tmpF = $minmaxFSize.attr('data-section-'+which+'-fontsize');
            minmaxFSize = parseFloat( tmpF );
            if (tmpF.match('em')) {
                minmaxFSize *= 12;
            } else if (tmpF.match('px') || !tmpF.match(/\D/)) {
                minmaxFSize *= 0.75;
            }
            mylog('minmaxFSize: ' + minmaxFSize + 'pt', false);
        }
        return minmaxFSize;
    }; // getMinMaxFontSize

} // LzyPresentationSupport
