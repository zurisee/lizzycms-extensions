// LiveData

"use strict";

var liveData = null;


function LiveData() {
    this.ajaxHndl = null;
    this.lastUpdated = 0;
    this.refs = '';
    this.elemInx = 0;
    this.liveDataUpdatingIsFine = true;


    this.init = function( execInitialDataUpload ) {
        const parent = this;
        this.refs = [];

        // collect all references within page:
        $('[data-ref]').each(function () {
            const $this = $( this );
            const dataRef = $this.attr('data-ref');
            if (!dataRef) {
                return;
            }

            parent.elemInx++;
            const srcRef = $this.closest('[data-datasrc-ref]').attr('data-datasrc-ref');

            let targSel = $this.attr('id');
            if (typeof targSel === 'undefined') {
                targSel = 'lzy-livedata-0' + parent.elemInx;
                $this.attr('id', targSel);
            }

            const compileMd = Boolean( $this.closest('.lzy-compile-md').length );
            parent.refs.push({
                srcRef: srcRef,
                dataRef: dataRef,
                targSel: '#' + targSel,
                md: compileMd,
            });
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
        let targSel, $rec = null;
        for (let i in lockedElements) {
            targSel = lockedElements[ i ];
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
        for (let i in frozenElements) {
            const targSel = frozenElements[ i ];
            if (targSel === '*') {
                $('.lzy-live-data').addClass('lzy-element-frozen');
            } else {
                $(targSel).addClass('lzy-element-frozen');
            }
        }
    }; // markFrozenFields



    this.updateDOM = function( data ) {
        const parent = this;
        this.markLockedFields(data.locked);
        this.markFrozenFields(data.frozen);
        if (typeof data.data !== 'object') {
            return;
        }

        let $targ = null;
        let updatedElems = '';
        let redrawDataTables = false;
        for (let targSel in data.data) {
            updatedElems += targSel + ' ';
            const val = data.data[targSel];

            // targSel can address multiple instances, therefore '.each()':
            $( targSel ).each( function() {
                $targ = $( this );

                // skip element if it's in active editable mode:
                if ($targ.hasClass('lzy-editable-active')) {
                    mylog('livedata: skipping active editable field: ' + targSel, false);
                    return;
                }

                let goOn = true;
                const callback = $targ.attr('data-live-callback');
                if (typeof window[callback] === 'function') {
                    mylog('livedata callback: ' + targSel + ' <= ' + val, false);
                    goOn = window[callback]( targSel, val );
                }

                if (goOn) {
                    let dataTable = false;
                    if (($targ.closest('.dataTable').length > 0)) {
                        const tableInx = $targ.closest('[data-inx]').attr('data-inx');
                        if (typeof tableInx !== 'undefined') {
                            dataTable = lzyTable[ parseInt(tableInx) ];
                            redrawDataTables = true;
                        }
                    }

                    const tag = $targ.prop('tagName');
                    const valTags = 'INPUT,SELECT,TEXTAREA';
                    if (dataTable) {
                        dataTable.cell( $targ[0] ).data( val );
                    } else {
                        if (valTags.includes(tag)) {
                            $targ.val(val);
                        } else {
                            $targ.html(val);
                        }
                    }
                    mylog('livedata:updateDOM: ' + targSel + ' <= ' + val, false);
                }
            });
        } // for

        // if DataTables are active, we need to redraw them:
        if (redrawDataTables) {
            mylog('liveData:updateDOM: redraw table', false);
            for (let i=1; true; i++) {
                const dataTable = lzyTable[i];
                if (typeof dataTable === 'undefined') {
                    break;
                }
                dataTable.draw();
            }
        }

        updatedElems = updatedElems.replace(/\[data-ref=/g, '');
        updatedElems = updatedElems.replace(/]/g, '');
        mylog('livedata updated: ' + updatedElems, false);

        if ($targ) {
            let postCallback = $targ.attr('data-live-post-update-callback');
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
        let data = null;
        mylog('livedata ajax response: ' + json, false);
        if (!json) {
            mylog('livedata: No data received - terminating live-data');
            return;
        }

        json = json.replace(/(.*[\]}])#.*/, '$1');    // remove trailing #comment
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
        } else if (data.result.match(/^fail/i)) {
            const m = data.result.match(/#(.*)/);
            mylog('livedata: _live_data_service.php reported an error \n==> ' + m[1]);
            return;
        } else if (data.result.match(/none/i)) {
            mylog('livedata updateDOM: ' + timeStamp() + ': No new data', false);
            // restart update cycle:
            this.updateLiveData();
            return;
        }

        // regular response with new data:
        let goOn = true;
        if (typeof liveDataCallback === 'function') {
            goOn = liveDataCallback( data.data );
        } else {
            const preCallback = $('[data-live-pre-update-callback]').attr('data-live-pre-update-callback');
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
        let refs = [],
            ref, i, $elem;

        // get refs, update currDataRef as it might have changed:
        for (i in this.refs) {
            ref = Object.assign({}, this.refs[ i ]);
            $elem = $( ref.targSel );
            ref.origDataRef =  ref.dataRef;
            ref.dataRef =  parent.resolveDataRef( $elem );
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
                mylog('-- signaling \'liveData still alive\' ' + timeStamp(true), false);
                parent.ajaxHndl = null;
                if (json !== 'abort') {
                    return parent.handleAjaxResponse(json);
                } else {
                    mylog('livedata aborted', false);
                }
            }
        });
    }; // updateLiveData




    this.resolveDataRef = function( $this ) {
        let $elem, tagName, s, m, rec, dataRecKey;
        let elem = '';
        const dataRef = $this.closest('[data-ref]').attr('data-ref');

        m = dataRef.match( /(.*?)(,.*)/ );
        if (m) {
            rec = m[1];
            elem = m[2];
        } else {
            rec = dataRef;
        }

        // check '=data-reckey':
        m = rec.match(/=([-\w]*)/);
        if (m) {
            dataRecKey = m[1]? m[1]: 'data-reckey';
            rec = $this.closest('[' + dataRecKey + ']').attr( dataRecKey );

        // check '#reckey' or '.reckey':
        } else {
            if (rec.match(/[#.][-\w]+/)) {
                if (rec.charAt(0) === '.') {
                    $elem = $this.closest( rec );
                } else {
                    $elem = $(rec);
                }
                tagName = $elem[0].tagName;
                if (tagName === 'SELECT') {
                    s = $(':selected', $elem).val();
                } else if (tagName === 'INPUT') {
                    s = $elem.val();
                } else {
                    s = $elem.text();
                }
                rec = s.trim();
            }
        }
        return rec + elem;
    }; // resolveDataRef



    this.dumpDB = function () {
        const parent = this;
        let url = appRoot + '_lizzy/extensions/livedata/backend/_live_data_service.php';
        $.ajax({
            url: url + "?dumpDB=true",
            type: 'POST',
            data: { ref: parent.refs },
            success: function (json) {
                let res = null;
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
        if (abortWatchdogs) { // global signal to abort all watchdogs
            mylog('-- liveUpdate Watchdog stopped');
            return;
        }
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
            url: appRoot + '_lizzy/_ajax_server.php?abort=true',
            type: 'GET',
            cache: false,
            success: function () {
                mylog('livedata: server process aborted');
            }
        });
    }; // abortAjax

} // LiveData



liveData = new LiveData();

function liveDataInit( execInitialDataUpload, activateWatchdog ) {
    liveData.init(execInitialDataUpload);

    if (typeof activateWatchdog !== 'undefined' && activateWatchdog) {
        mylog('-- starting liveData watchdog ' + timeStamp(true));
        liveData.startWatchdog();
    }
} // liveDataInit




$(window).bind('beforeunload', function() {
    if (liveData) {
        liveData.abortAjax();
    }
});


