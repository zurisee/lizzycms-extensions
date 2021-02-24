// LiveData
//  Note: requires explicit invokation in livedata.php, resp. editable module

"use strict";

var liveData = null;


function LiveData() {
    this.ajaxHndl = null;
    this.lastUpdated = 0;
    this.refs = '';
    this.elemInx = 0;
    this.liveDataUpdatingIsFine = true;


    this.init = function( execInitialDataUpload ) {
        var parent = this;
        this.refs = [];

        // collect all references within page:
        $('[data-ref]').each(function () {
            const $this = $( this );
            var dataRef = $this.attr('data-ref');
            if (!dataRef) {
                return;
            }

            parent.elemInx++;

            // check dataRef for '=' -> indirect addressing of data element:
            var m = dataRef.match(/=([-\w]*)/);
            if (m) {
                var patt = m[1]? m[1]: 'data-reckey';
                var str = $this.attr( patt );
                if (typeof str === 'undefined') {
                    str = $this.closest('[' + patt + ']').attr('data-reckey');
                }
                var re = new RegExp( m[0] );
                dataRef = dataRef.replace(re, str);
            }
            var srcRef = $this.attr('data-datasrc-ref');
            if (typeof srcRef === 'undefined') {
                srcRef = $this.closest('[data-datasrc-ref]').attr('data-datasrc-ref');
            }

            var targSel = $this.attr('id');
            if (typeof targSel === 'undefined') {
                targSel = 'lzy-livedata-0' + parent.elemInx;
                $this.attr('id', targSel);
            }

            var compileMd = Boolean( $this.closest('.lzy-compile-md').length );
            parent.refs.push({ srcRef: srcRef, dataRef: dataRef, targSel: targSel, md: compileMd });
        });

        if ((typeof execInitialDataUpload !== 'undefined') && !execInitialDataUpload) {
            this.lastUpdated = -1;
        }

        // start update cycle:
        this.updateLiveData();
    }; // init



    this.markLockedFields = function( lockedElements ) {
        if (typeof lockedElements === 'undefined') {
            return;
        }

        $('.lzy-element-locked').removeClass('lzy-element-locked');

        if (typeof lockedElements !== 'object') {
            return;
        }
        var targSel, $rec = null;
        for (var i in lockedElements) {
            targSel = lockedElements[i];
            mylog('livedata markLockedFields: ' + targSel, false);
            $rec = $( targSel );  // direct hit
            if (!$rec.length) {
                $rec = $('[data-ref="' + targSel + '"]'); // if [data-ref] missing
            }
            if (!$rec.length) {
                $rec = $('[data-reckey="' + i + '"]');  // rec addresses
            }
            if ($rec.length) {
                $rec.addClass('lzy-element-locked');
            } else {
                if (targSel === '*') {
                    $('.lzy-live-data').addClass('lzy-element-locked');
                } else {
                    $rec = $(targSel).closest('[data-reckey]');
                    if ($rec.length) {
                        $rec.addClass('lzy-element-locked');
                    } else {
                        $(targSel).addClass('lzy-element-locked');
                    }
                }
            }
        }
    }; // markLockedFields



    this.markFrozenFields = function( frozenElements ) {
        if (typeof frozenElements === 'undefined') {
            return;
        }

        $('.lzy-element-locked').removeClass('lzy-element-locked');

        if (typeof frozenElements !== 'object') {
            return;
        }
        for (var i in frozenElements) {
            var targSel = frozenElements[i];
            if (targSel === '*') {
                $('.lzy-live-data').addClass('lzy-element-frozen');
            } else {
                $(targSel).addClass('lzy-element-frozen');
            }
        }
    }; // markFrozenFields



    this.updateDOM = function( data ) {
        this.markLockedFields(data.locked);
        this.markFrozenFields(data.frozen);
        if (typeof data.data !== 'object') {
            return;
        }

        var $targ = null;
        var updatedElems = '';
        for (var targSel in data.data) {
            updatedElems += targSel + ' ';
            var val = data.data[targSel];

            // targSel can address multiple instances, therefore '.each()':
            $( targSel ).each( function() {
                $targ = $( this );

                // skip element if it's in active editable mode:
                if ($targ.hasClass('lzy-editable-active')) {
                    mylog('livedata: skipping active editable field: ' + targSel, false);
                    return;
                }

                var goOn = true;
                var callback = $targ.attr('data-live-callback');
                if (typeof window[callback] === 'function') {
                    mylog('livedata callback: ' + targSel + ' <= ' + val, false);
                    goOn = window[callback]( targSel, val );
                }

                if (goOn) {
                    var tag = $targ.prop('tagName');
                    var valTags = 'INPUT,SELECT,TEXTAREA';
                    if (valTags.includes(tag)) {
                        $targ.val(val);
                    } else {
                        $targ.html(val);
                    }
                    mylog('livedata updateDOM: ' + targSel + ' <= ' + val, false);
                }
            });
        } // for

        updatedElems = updatedElems.replace(/\[data-ref=/g, '');
        updatedElems = updatedElems.replace(/]/g, '');
        mylog('livedata updated: ' + updatedElems);

        if ($targ) {
            var postCallback = $targ.attr('data-live-post-update-callback');
            if (typeof postCallback === 'undefined') {
                postCallback = $targ.closest('[data-live-post-update-callback]').attr('data-live-post-update-callback');
            }
            if (typeof postCallback === 'undefined') {
                postCallback = $('[data-live-post-update-callback]').attr('data-live-post-update-callback');
            }
            if ((typeof postCallback !== 'undefined') && (typeof window[postCallback] === 'function')) {
                window[postCallback]( data.data );
            }
        }
    }; // updateDOM



    this.handleAjaxResponse = function( json ) {
        var data = null;
        mylog('livedata ajax response: ' + json, false);
        if (!json) {
            mylog('livedata: No data received - terminating live-data');
            return;
        }

        json = json.replace(/(.*[\]}])\#.*/, '$1');    // remove trailing #comment
        try {
            data = JSON.parse( json );
        } catch (e) {
            mylog('livedata: Error condition detected - terminating live-data \n==> ' + json);
            return false;
        }

        if (typeof data.lastUpdated !== 'undefined') {
            this.lastUpdated = data.lastUpdated;
        }
        if (typeof data.result === 'undefined') {
            mylog('livedata: _live_data_service.php reported an error \n==> ' + json);
            return;
        }

        // regular response:
        if (typeof data.data === 'undefined') {
            mylog('livedata updateDOM: ' + timeStamp() + ': No new data', false);

        } else {
            var goOn = true;
            if (typeof liveDataCallback === 'function') {
                goOn = liveDataCallback( data.data );
            } else {
                var preCallback = $('[data-live-pre-update-callback]').attr('data-live-pre-update-callback');
                if ((typeof preCallback !== 'undefined') && (typeof window[preCallback] === 'function')) {
                    goOn = window[preCallback]( data.data );
                }
            }
            if (goOn) {
                this.updateDOM(data);
            }
            if (typeof liveDataPostUpdateCallback === 'function') {
                liveDataPostUpdateCallback( data.data );
            }
        }
        $('.live-data-update-time').text( timeStamp() );

        // restart update cycle:
        this.updateLiveData();
    }; // handleAjaxResponse



    this.updateLiveData = function( immediately ) {
        const parent = this;
        const url = appRoot + '_lizzy/extensions/livedata/backend/_live_data_service.php';

        if ((typeof immediately !== 'undefined') && immediately) {
            this.lastUpdated = 0;
        }

        var dataRef = null,
            refs = [],
            ref = null,
            m = null,
            s = null,
            i = null,
            tagName = null,
            $elem = null;

        for (i in this.refs) {
            ref = Object.assign({}, this.refs[ i ]);
            dataRef = ref.dataRef;

            // option: dataRef may contain selector from which to optain actual value:
            if ((dataRef.charAt(0) === '#') || (dataRef.charAt(0) === '.')) {
                m = dataRef.match( /(.*?),(.*)/ );
                if (m) {
                    $elem = $( m[1] );
                    tagName = $elem[0].tagName;
                    if (tagName === 'SELECT') {
                        s = $(':selected', $elem).val();
                    } else if (tagName === 'INPUT') {
                        s = $elem.val();
                    } else {
                        s = $elem.text();
                    }
                    ref.dataRef = s.trim() + ',' + m[2];
                } else {
                    ref.dataRef = dataRef;
                }
                $elem = $( '[data-ref="'+dataRef+'"]' );
                ref.id = '#' + $elem.attr('id');
            }
            refs[ i ] = ref;
        }

        // before starting update cycle, abort previous one if still running:
        if (this.ajaxHndl !== null) {
            this.ajaxHndl.abort();
        }

        // call host for data update:
        this.ajaxHndl = $.ajax({
            url: url,
            type: 'POST',
            data: {
                ref: JSON.stringify( refs ),
                lastUpdated: parent.lastUpdated
            },
            success: function (json) {
                parent.liveDataUpdatingIsFine = true; // signal 'still alive'
                mylog("-- signaling 'liveData still alive' " + timeStamp(true));
                parent.ajaxHndl = null;
                if (json !== 'abort') {
                    return parent.handleAjaxResponse(json);
                } else {
                    mylog('livedata aborted');
                }
            }
        });
    }; // updateLiveData



    this.dumpDB = function () {
        var parent = this;
        var url = appRoot + "_lizzy/extensions/livedata/backend/_live_data_service.php";
        $.ajax({
            url: url + "?dumpDB=true",
            type: 'POST',
            data: { ref: parent.refs },
            success: function (json) {
                var res = null;
                try {
                    res = JSON.parse( json );
                } catch (e) {
                    mylog('livedata: Error condition detected - terminating live-data \n==> ' + json);
                    return false;
                }
                const text = atob( res.data );
                mylog( 'livedata ajax response: ' + text, false );
            }
        });
    }; // dumpDB
    
    
    
    this.startWatchdog = function() {
        const parent = this;
        setTimeout(function () {
            if (!parent.liveDataUpdatingIsFine) {
                if (parent.ajaxHndl === null) {
                    mylog('#### watchdog restarts liveData update cycle');
                    parent.updateLiveData();
                }
            }
            mylog('-- restarting liveData watchdog ' + timeStamp(true));
            parent.liveDataUpdatingIsFine = false;
            parent.startWatchdog();
        }, 120000); // 2 minutes
    }; // startWatchdog



    this.abortAjax = function() {
        if (this.ajaxHndl !== null) {
            this.ajaxHndl.abort();
            this.ajaxHndl = null;
            mylog('livedata: last ajax call aborted');
        }
        $.ajax({
            url: appRoot + "_lizzy/_ajax_server.php?abort=true",
            type: 'GET',
            cache: false,
            success: function () {
                mylog('livedata: server process aborted');
            }
        });
    }; // abortAjax

} // LiveData





function liveDataInit( execInitialDataUpload, activateWatchdog ) {
    if (!liveData) {
        liveData = new LiveData();
    }
    liveData.init( execInitialDataUpload );
    
    if (typeof activateWatchdog !== 'undefined' && activateWatchdog) {
        mylog('-- starting liveData watchdog ' + timeStamp(true));
        liveData.startWatchdog();
    }
    return liveData;
} // liveDataInit




$(window).bind('beforeunload', function() {
    if (liveData) {
        liveData.abortAjax();
    }
});


