// LiveData
//  Note: requires explicit invokation in livedata.php, resp. editable module

"use strict";

var LiveData = new Object({
    ajaxHndl: null,
    lastUpdated: 0,
    paramName: '',
    paramSource: '',
    prevParamValue: '',
    refs: '',
    debugOutput: false,

    init: function( execInitialUpdate ) {
        var rootObj = this;
        // collect all references within page:
        $('[data-lzy-datasrc-ref]').each(function () {
            var ref = $( this ).attr('data-lzy-datasrc-ref');
            rootObj.refs = rootObj.refs + ref + ',';
        });
        this.refs = this.refs.replace(/,+$/, '');


        // check for dynamic parameter: (only apply first appearance)
        $('[data-live-data-param]').each(function () {
            var paramDef = $( this ).attr('data-live-data-param');
            paramDef = paramDef.split('=');
            rootObj.paramName = paramDef[0];
            rootObj.paramSource = paramDef[1];
            console.log('custom param: ' + rootObj.paramSource + ' => ' + rootObj.paramName);
            return false;
        });

        this.debugOutput = ($('.debug').length !== 0);

        if ((typeof execInitialUpdate !== 'undefined') && !execInitialUpdate) {
            this.lastUpdated = -1;
        }

        this.updateLiveData(  );
    }, // init




    markLockedFields: function( lockedElements ) {
        $('.lzy-element-locked').removeClass('lzy-element-locked');

        if (typeof lockedElements !== 'object') {
            return;
        }
        for (var i in lockedElements) {
            var targSel = lockedElements[i];
            if (targSel === '*') {
                $('.lzy-live-data').addClass('lzy-element-locked');
            } else {
                $(targSel).addClass('lzy-element-locked');
            }
        }
    }, // markLockedFields




    markFrozenFields: function( frozenElements ) {
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
    }, // markFrozenFields




    updateDOM: function( data ) {
        if (typeof data.locked !== 'undefined') {
            this.markLockedFields(data.locked);
        }

        if (typeof data.frozen !== 'undefined') {
            this.markFrozenFields(data.frozen);
        }

        if (typeof data.data !== 'object') {
            return;
        }
        var $targ = null;
        for (var targSel in data.data) {
            var val = data.data[targSel];
            //mylog(`writing to '${targSel}' value '${val}'`);
            $( targSel ).each(function() {
                $targ = $( this );
                const id = $targ.attr('id');

                // skip element if it's in active editable mode:
                if ($targ.hasClass('lzy-editable-active')) {
                    mylog('live-data: skipping active editable field: ' + id);
                    return;
                }

                var goOn = true;
                var callback = $targ.attr('data-live-callback');
                if (typeof window[callback] === 'function') {
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
                }
            });

            if (this.debugOutput) {
                console.log(targSel + ' -> ' + val);
            }
        }

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
    }, // updateDOM




    handleAjaxResponse: function( json ) {
        this.ajaxHndl = null;
        var data = null;
        console.log('ajax: ' + json);
        if (!json) {
            console.log('No data received - terminating live-data');
            return;
        }
        json = json.replace(/(.*[\]}])\#.*/, '$1');    // remove trailing #comment
        try {
            data = JSON.parse( json );
        } catch (e) {
            console.log('Error condition detected - terminating live-data');
            console.log(json);
            return false;
        }

        if (typeof data.lastUpdated !== 'undefined') {
            this.lastUpdated = data.lastUpdated;
        }
        if (typeof data.result === 'undefined') {
            console.log('_live_data_service.php reported an error');
            console.log(json);
            return;
        }

        // regular response:
        if (typeof data.data === 'undefined') {
            if (this.debugOutput) {
                console.log(timeStamp() + ': No new data');
            }

        } else {
            var goOn = true;
            if (typeof liveDataCallback === 'function') {
                goOn = liveDataCallback( data.data );
            }
            if (goOn) {
                this.updateDOM(data);
            }
            if (typeof liveDataPostUpdateCallback === 'function') {
                liveDataPostUpdateCallback( data.data );
            }
        }
        $('.live-data-update-time').text( timeStamp() );

        if ( $('body').hasClass('debug') ) {
            this.dumpDB();
        }

        this.updateLiveData();
    }, // handleAjaxResponse




    updateLiveData: function() {
        const rootObj = this;
        const url = appRoot + "_lizzy/extensions/livedata/backend/_live_data_service.php";
        var data = {
            ref: rootObj.refs,
            lastUpdated: rootObj.lastUpdated
        };

        if (this.paramName) {
            var paramValue = $( this.paramSource ).text();
            data.dynDataSel = this.paramName + ':' + paramValue;
            if (paramValue !== this.prevParamValue) {
                this.prevParamValue = paramValue;
                data.lastUpdated = 0;
            }
        }
        if (this.ajaxHndl !== null){
            this.ajaxHndl.abort();
        }
        this.ajaxHndl = $.ajax({
            url: url,
            type: 'POST',
            data: data,
            success: function (json) {
                return rootObj.handleAjaxResponse(json);
            }
        });
    }, // updateLiveData




    dumpDB: function () {
        var rootObj = this;
        var url = appRoot + "_lizzy/extensions/livedata/backend/_live_data_service.php";
        $.ajax({
            url: url + "?dumpDB=true",
            type: 'POST',
            data: { ref: rootObj.refs },
            success: function (json) {
                var res = null;
                try {
                    res = JSON.parse( json );
                } catch (e) {
                    console.log('Error condition detected - terminating live-data');
                    console.log(json);
                    return false;
                }
                const text = atob( res.data );
                console.log( text );
            }
        });
    },



    abortAjax: function() {
        if (this.ajaxHndl !== null){
            this.ajaxHndl.abort();
            this.ajaxHndl = null;
            console.log('live-data: last ajax call aborted');
        }
        $.ajax({
            url: appRoot + "_lizzy/_ajax_server.php?abort=true",
            type: 'GET',
            cache: false,
            success: function (json) {
                console.log('live-data: server process aborted');
            }
        });
    } // abortAjax

}); // Object LiveData




$(window).bind('beforeunload', function() {
    LiveData.abortAjax();
});


