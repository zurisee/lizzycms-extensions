/*

    Inspired by: https://www.barrierefreies-webdesign.de/knowhow/tablist/tabpanel-links-tabindex.html

*/


if (typeof window.widthThreshold == 'undefined') {
    window.widthThreshold = 480;
}
panelWidgetInstance = 1;    // instance number

$( document ).ready(function() {

    initPanels();
    setPanelHeights();
    setupEvents();

    $( window ).resize( onResize );
    onResize();

    scrollToWidget();
});





function initPanels()
{
    var $widgets = $('.lzy-panels-widget');
    $widgets.each(function () {
        $this = $( this );
        $this.attr('id', 'lzy-panels-widget' + panelWidgetInstance);
        $this.addClass('lzy-panels-widget' + panelWidgetInstance);

        var panels = [];
        i = 0;
        $('> *', $(this)).each(function () {
            var $this = $( this );
            var hdrText = $('> *:first-child', $this).text();
            var body = '';
            $('> *:not(:first-child)', $this).each(function () {
                body += $(this)[0].outerHTML;
            });
            panels[i] = {hdrText: hdrText, body: body};
            i++;
        });

        var header = '';
        var body = '';

        for (i = 0; i < panels.length; ++i) {
            var panel = panels[i];
            var i1 = panelWidgetInstance*100 + i + 1;
            var tabindex = (i == 0) ? '0' : '-1';

            header += '\t\t<li id="lzy-tabs-mode-panel-header-id' + i1 + '" class="lzy-tabs-mode-panel-header" role="tab" aria-controls="lzy-panel-id' + i1 + '" aria-selected="false" tabindex="'+ tabindex +'">' + panel.hdrText + '</li>\n';

            body += '\n\n\t<!-- === panel page ==== -->\n\t<div id="lzy-panel-id' + i1 + '" class="lzy-panel-page" role="tabpanel" tabindex="-1" aria-labelledby="lzy-tabs-mode-panel-header-panel-id' + i1 + '" aria-hidden="true">\n' +
                '\t\t<div class="lzy-accordion-mode-panel-header"><a id="#lzy-panel-controller' + i1 + '" href="#lzy-panel-id' + i1 + '" class="lzy-panel-link" aria-controls="lzy-panel-body-wrapper' + i1 + '" aria-expanded="false">' + panel.hdrText + '</a></div>\n' +
                '\t\t<div id="lzy-panel-body-wrapper' + i1 + '" class="lzy-panel-body-wrapper" aria-labelledby="lzy-panel-controller' + i1 + '" role="region">\n' +
                '\t\t\t<div class="lzy-panel-inner-wrapper">\n' +
                panel.body +
                '\t\t\t</div><!-- /lzy-panel-inner-wrapper -->\n' +
                '\t\t</div><!-- /lzy-panel-body-wrapper -->\n' +
                '\t</div><!-- /lzy-panel-page -->\n';
        }
        header = '\n\n<!-- === lzy-tabs-mode headers ==== -->\n\t<ul class="lzy-tabs-mode-panels-header-list" role="tablist">\n' + header + '\t</ul>\n\n';

        $(this).html(header + body);
        $('.lzy-panel-page', $(this)).first().attr('aria-hidden', 'false');
        $('.lzy-tabs-mode-panel-header:first-child', $(this)).attr('aria-selected', 'true');

        panelWidgetInstance++;
    });
}




function setupEvents() {
    setupTabsHeaderEvents();
    setupAccordionHeaderEvents();
    setupKeyboardEvents();
}



function setupTabsHeaderEvents()
{
    $('.lzy-tabs-mode-panel-header').click(function() {
        var id = '#' + $( this ).attr('aria-controls');
        // console.log('tabs event: '+id);
        operatePanel( id, true);
    });
}



function setupAccordionHeaderEvents()
{
    var mousedown = false;
    var $accordionHeaders = $('.lzy-accordion-mode-panel-header');
    $accordionHeaders.on('mousedown', function () {
        mousedown = true;
    });
    $accordionHeaders.on('focusin', function () {
        if(!mousedown) {
            var id = $('a', $( this )).attr('href');
            // console.log('acc keyb event: '+id);
            operatePanel( id, false);
            return;
        }
        mousedown = false;
    });
    $accordionHeaders.click(function(e) {
        e.preventDefault();
        mousedown = false;
        var id = $('a', $( this )).attr('href');
        // console.log('acc click event: '+id);
        operatePanel( id, false);
    });
}

function operatePanel( id, tabClicked)
{
    var oneOpenOnly = ($(id).closest('.lzy-panels-widget.one-open-only').length > 0);

    if (tabClicked) {                   // Click/focus on Tab
        // close all, open id
        var tabsHdrId = id.replace(/panel-/, 'lzy-tabs-mode-panel-header-');
        var wasOpen = ($(tabsHdrId).attr('aria-selected') == 'true');
        if (!wasOpen) {
            closeAllPanels( id );
            openPanel( id );
        }

    } else {                            // click/focus on Accordion-header
        var wasOpen = $(id).hasClass('lzy-panel-page-open');
        if (wasOpen) {
            closePanel( id );
        } else {
            if (oneOpenOnly) {   // close all, open id
                closeAllPanels(id);
                openPanel( id );
            } else {            // close id
                openPanel( id );
            }
        }
    }
}


function closeAllPanels( id ) {
    closeAllTabs( id );
    var $thisWidget = $(id).closest('.lzy-panels-widget');

    var $panelHdrs = $('.lzy-panel-page', $thisWidget);
    $panelHdrs.attr({ 'aria-hidden': 'true', 'aria-expanded': 'false', 'aria-selected':'false'});
    $panelHdrs.removeClass('lzy-panel-page-open');
}


function closeAllTabs( id )
{
    var $thisWidget = $(id).closest('.lzy-panels-widget');
    var $tabsHdrs = $('.lzy-tabs-mode-panel-header', $thisWidget);
    $tabsHdrs.attr({'aria-selected': 'false', 'tabindex': -1});

    var $panelHdrs = $('.lzy-panel-page', $thisWidget);
    $panelHdrs.attr({ 'aria-hidden': 'true', 'aria-selected':'false'});
}


function openPanel( id )
{
    closeAllTabs( id );
    openTab( id );

    var $panelHdr = $( id );
    $panelHdr.attr({ 'aria-hidden': 'false', 'aria-expanded': 'true', 'aria-selected':'true'});
    $panelHdr.addClass('lzy-panel-page-open');
}


function openTab( id )
{
    var tabsHdrId = id.replace(/lzy-panel-/, 'lzy-tabs-mode-panel-header-');
    $(tabsHdrId).attr({'aria-selected': 'true', 'tabindex': 0});

    var $panelHdr = $( id );
    $panelHdr.attr({ 'aria-hidden': 'false', 'aria-selected':'true'});
}

function closeTab( id )
{
    var tabsHdrId = id.replace(/lzy-panel-/, 'lzy-tabs-mode-panel-header-');
    $(tabsHdrId).attr({'aria-selected': 'false', 'tabindex': -1});

    var $panelHdr = $( id );
    $panelHdr.attr({ 'aria-hidden': 'true', 'aria-selected':'false'});
}

function closePanel( id )
{
    var tabsHdrId = id.replace(/lzy-panel-/, 'lzy-tabs-mode-panel-header-');
    $(tabsHdrId).attr({'aria-selected': 'false', 'tabindex': -1});

    var $panelHdr = $( id );
    $panelHdr.attr({ 'aria-hidden': 'true', 'aria-expanded': 'false', 'aria-selected':'false'});
    $panelHdr.removeClass('lzy-panel-page-open');

    var $thisWidget = $(id).closest('.lzy-panels-widget');
    var nSelected = $('.lzy-tabs-mode-panel-header[aria-selected=true]', $thisWidget).length;
    if (nSelected == 0) {
        id = id.substr(0,10) + '01';     // open first panel
        openTab( id );
    }
}




function setPanelHeights()
{
    $('.lzy-panels-widget:not(.lzy-tab-mode) .lzy-accordion-mode-panel-header').each(function(e) {
        var $this = $(this);
        var idBody = '#'+$('a', $this).attr('aria-controls');
        var $innerWrapper = $(idBody + ' .lzy-panel-inner-wrapper');
        var h = $innerWrapper.outerHeight();
        $innerWrapper.css('margin-top', '-' + h + 'px');
    });
}


function onResize() {
    function getWidthOfTabs() {
        var w = 0;
        $('.lzy-tabs-mode-panels-header-list').css('display', 'block');
        $('.lzy-tabs-mode-panel-header').each(function () {
            var w1 = $(this).css('display', 'inline-block').outerWidth();
            w = w + w1;
        });
        $('.lzy-tabs-mode-panels-header-list').css('display', '');
        return w;
    }

    setMode();  // Accordion/Tabs or auto depending on window width

    setPanelHeights();
}



function setMode() {
    $('.lzy-panels-widget').each(function () {
        $this = $( this );
        if ($this.hasClass('lzy-accordion')) {
            $this.removeClass('lzy-tab-mode');

        } else if ($this.hasClass('lzy-tabs')) {
            $this.addClass('lzy-tab-mode');

        } else {
            switchOnWidthThreshold( $this );
        }

    });
}



function switchOnWidthThreshold( $panelWidget ) {
    var windowWidth = $(window).width();
    var threshold = parseInt(window.widthThreshold);
    if (windowWidth < threshold) {
        $panelWidget.removeClass('lzy-tab-mode');
    } else {
        $panelWidget.addClass('lzy-tab-mode');
    }
}



function setupKeyboardEvents()
{
    // focus is on tab header -> switch between tabs:   left/right and home/end cursor keys
    $('.lzy-tabs-mode-panel-header').keydown( function( event ) {
        var keyCode = event.keyCode;
        var id = '#' + $( this ).attr('id').replace(/lzy-tabs-mode-panel-header-/, 'lzy-panel-');
        id = id.substr(0, id.length-2);
        var idN = parseInt($( this ).attr('id').substr(-2));

        // console.log('id: ' + id + ' idN: ' + idN  + ' keyCode: ' + keyCode);
        if (keyCode == 39) {    // right arrow
            event.preventDefault();
            var idN1 = (idN + 1);
            idN1 = (idN1 > 9) ? idN1 : '0' + idN1;
            var id1 = id + idN1;
            if (!$( id1 ).length) {
                id1 = id + '01';
            }
            // console.log( id1 );
            openPanel( id1 );
            $( id1.replace(/lzy-panel-/, 'lzy-tabs-mode-panel-header-')).focus();

        } else if (keyCode == 37) {    // left arrow
            event.preventDefault();
            if (idN > 1) {
                var idN1 = (idN - 1);
                idN1 = (idN1 > 9) ? idN1 : '0' + idN1;
                var id1 = id + idN1;
            } else {
                $last = $('.lzy-tabs-mode-panel-header:last-child', $(this).parent());
                var id1 = '#' + $last.attr('id').replace(/lzy-tabs-mode-panel-header-/, 'lzy-panel-');
            }
            // console.log( id1 );
            openPanel( id1 );
            $( id1.replace(/lzy-panel-/, 'lzy-tabs-mode-panel-header-')).focus();

        } else if (keyCode == 36) {    // home key
            event.preventDefault();
            var id1 = id + '01';
            // console.log( id1 );
            openPanel( id1 );
            $( id1.replace(/lzy-panel-/, 'lzy-tabs-mode-panel-header-')).focus();

        } else if (keyCode == 35) {    // left arrow
            event.preventDefault();
            $last = $('.lzy-tabs-mode-panel-header:last-child', $(this).parent());
            var id1 = '#' + $last.attr('id').replace(/lzy-tabs-mode-panel-header-/, 'lzy-panel-');
            // console.log( id1 );
            openPanel( id1 );
            $( id1.replace(/lzy-panel-/, 'lzy-tabs-mode-panel-header-')).focus();

        }
    });

/*  To Do: add support for ^left/^up, ^right/^down when inside panel pages

    // focus is inside panel page -> switch between pages: ^left/^up, ^right/^down
    $('.lzy-panel-inner-wrapper').keydown( function( event ) {
        var keyCode = event.keyCode;
        var id = $( this ).closest('.lzy-panel-page').attr('id');
        console.log(id + ' : ' + keyCode);
        if ((event.ctrlKey || macKeys.ctrlKey) && keyCode == 39) {    // right arrow
            event.preventDefault();
            console.log(id + ' right arrow');
        }
    });
*/
}




function scrollToWidget() {
    var hash = window.location.hash;
    if (hash && $(hash).length) {
        openPanel(hash);
        var $widget = $(hash).closest('.lzy-panels-widget');
        $widget[0].scrollIntoView();
    }
}


