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
        this.initSlides();
        this.initPresiElements();
        this.initEventHandlers();
        this.resizeSections();
        this.revealPresentationElement();
    }; // init



    this.initSlides = function () {
        const bg = $('.lzy-main').css('background-color');
        if (bg === 'rgb(255, 255, 255)') { // apply scroll-hints only on white background
            $('.lzy-section').addClass('lzy-slide lzy-scroll-hints');
        } else {
            $('.lzy-section').addClass('lzy-slide');
        }
        $('.lzy-slide').wrapInner('<div class="lzy-slide-inner-wrapper"></div>');
        $('.lzy-slide-inner-wrapper').prepend('<br />'); // prevent margin-collapse

        // attempt to overcome ios 100vh bug:
        if ($('body').hasClass('touch')) {
            $('.lzy-slide-inner-wrapper').append('<div class="lzy-ios-bottom-gap"></div>'); // prevent margin-collapse
        }
    }; // initSlides



    this.initPresiElements = function(){
        let $sections = $('.lzy-slide');
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

        // Following feature unfinished:
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
        const bodyW = $(window).width();
        const bodyH = $(window).height();
        mylog(`Body: w: ${bodyW}  h: ${bodyH}`, false);

        const $slideWrapper = $('.lzy-slide');
        const availableW = $slideWrapper.width() + 1;
        const availableH = $slideWrapper.height() + 1 - 60;
        // const availableH = $slideWrapper.height() + 1;
        mylog(`Main: w: ${availableW}  h: ${availableH}`, false);

        const $slides = $('.lzy-slide-inner-wrapper');

        let maxFSize = 40;
        let minFSize = 20;

        const maxFSize0 = this.convertToPixel(maxFSize);
        const minFSize0 = this.convertToPixel(minFSize);

        // loop over all slides:
        $slides.each(function () {
            const $slide = $(this);

            minFSize = minFSize0;
            maxFSize = maxFSize0;
            const $fontSize = $('[data-font-size]', $slide);
            if ($fontSize.length) {
                const fixFontSize = parent.convertToPixel($fontSize.attr('data-font-size'));
                $slide.css({fontSize: fixFontSize + 'px'});
                mylog(`final (fixed): ${fixFontSize}px`, false);
                return;
            }

            const $min = $('[data-min-font-size]', $slide);
            if ($min.length) {
                minFSize = parent.convertToPixel($min.attr('data-min-font-size'));
                mylog(`min fontsize: ${minFSize}px`, false);
            }
            const $max = $('[data-max-font-size]', $slide);
            if ($max.length) {
                maxFSize = parent.convertToPixel($max.attr('data-max-font-size'));
                mylog(`max fontsize: ${maxFSize}px`, false);
            }

            let fSize = maxFSize;
            let step = (maxFSize - minFSize) * 0.5;
            while (true) {
                $slide.css({fontSize: fSize + 'px'});

                let w = $slide.width();
                let h = $slide.height();
                let dx = (availableW - w);
                let dy = (availableH - h);
                if ((dx > 0) && (dy > 0)) {
                    mylog(`up: ${step}  fSize: ${fSize}  ${w} ${dx}`, false);
                    fSize += step;
                } else {
                    mylog(`down: ${step}  fSize: ${fSize}  ${w} ${dx}`, false);
                    fSize -= step;
                }
                if (fSize < minFSize) {
                    fSize = minFSize;
                    mylog(`final (min): ${fSize}`, false);
                    return;
                } else if (fSize > maxFSize) {
                    fSize = maxFSize;
                    mylog(`final (max): ${fSize}`, false);
                    return;
                }

                step = step * 0.5;
                if (step < 0.5) {
                    mylog(`w: ${dx}  h: ${dy}  fSize: ${fSize}  step: ${step}`, false);
                    mylog(`final: ${fSize}`, false);
                    break;
                }
            }

            mylog('Phase 2:', false);
            fSize += 0.5;
            for (let i = 0; i < 20; i++) {
                $slide.css({fontSize: fSize + 'px'});
                let w = $slide.width();
                let h = $slide.height();
                let dx = (availableW - w);
                let dy = (availableH - h);
                mylog(`w: ${dx}  h: ${dy}  fSize: ${fSize}`, false);
                if ((dx > 0) && (dy > 0)) {
                    mylog(`final: ${fSize}`, false);
                    break;
                }
                fSize -= 0.1;
                if (fSize < minFSize) {
                    fSize = minFSize;
                    mylog(`final (min): ${fSize}`, false);
                    break;
                }
            }
            const scaleFactor = parent.getScaleFactor( $slide );
            if (scaleFactor) {
                fSize  *= scaleFactor;
                $slide.css({fontSize: fSize + 'px'});
            }

            mylog('main w: ' + $slide.width() + '  h: ' + $slide.width(), false);
            mylog(`font-size: ${fSize}`);
        });
    }; // resizeSections



    this.getScaleFactor = function( $section ) {
        let scaleFactor = false;
        const $scaleFactor = $('[data-scale-factor]', $section);
        if ($scaleFactor.length) {
            const scaleF = $scaleFactor.attr('data-scale-factor');
            scaleFactor = parseFloat( scaleF );
        }
        return scaleFactor;
    }; // getScaleFactor



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
        }
        return minmaxFSize;
    }; // getMinMaxFontSize



    this.convertToPixel = function( value ) {
        if (typeof value === 'string') {
            if (value.match('px')) {
                value = parseFloat(value);

            } else if (value.match('em')) {
                value = parseFloat(value) * 16;

            } else if (value.match('pt')) {
                value = parseFloat(value) / 3 * 4;

            } else if (value.match('vw')) {
                const bodyW = $(window).width();
                value = parseFloat(value) * bodyW / 100;

            } else if (value.match('vh')) {
                const bodyH = $(window).height();
                value = parseFloat(value) * bodyH / 100;
            }
        } else if (typeof value === 'number') {
            value = parseFloat(value);
        }
        return value;
    }; // convertToPixel

} // LzyPresentationSupport
