// LiveData
//  Note: requires explicit invokation in livedata.php, resp. editable module

var ajaxHndl = null;
var lastUpdated = 0;
var paramName = '';
var paramSource = '';
var prevParamValue = '';
var refs = '';
var debugOutput = false;


function initLiveData() {
    // collect all references within page:
    $('[data-lzy-data-ref]').each(function () {
        var ref = $( this ).attr('data-lzy-data-ref');
        refs = refs + ref + ',';
    });
    refs = refs.replace(/,+$/, '');


    // check for dynamic parameter: (only apply first appearance)
    $('[data-live-data-param]').each(function () {
        var paramDef = $( this ).attr('data-live-data-param');
        paramDef = paramDef.split('=');
        paramName = paramDef[0];
        paramSource = paramDef[1];
        console.log('custom param: ' + paramSource + ' => ' + paramName);
        return false;
    });

    debugOutput = ($('.debug').length !== 0);

    updateLiveData( true );
} // init




function markLockedFields( lockedElements ) {
    // console.log('updating locked states ' + lockedElements);
    $('.lzy-element-locked').removeClass('lzy-element-locked');
// $('.lzy-live-data').removeClass('lzy-element-locked');

    for (var i in lockedElements) {
        var targSel = lockedElements[ i ];
        if (targSel === '*') {
            $('.lzy-live-data').addClass('lzy-element-locked');
        } else {
            $( targSel ).addClass('lzy-element-locked');
        }
    }
} // markLockedFields




function markFrozenFields( frozenElements ) {
    // console.log('updating locked states ' + frozenElements);
    $('.lzy-element-locked').removeClass('lzy-element-locked');
// $('.lzy-live-data').removeClass('lzy-element-locked');

    for (var i in frozenElements) {
        var targSel = frozenElements[ i ];
        if (targSel === '*') {
            $('.lzy-live-data').addClass('lzy-element-frozen');
        } else {
            $( targSel ).addClass('lzy-element-frozen');
        }
    }
} // markFrozenFields




function updateDOM(data) {
    if (typeof data.locked !== 'undefined') {
        markLockedFields(data.locked);
    }

    if (typeof data.frozen !== 'undefined') {
        markFrozenFields(data.frozen);
    }

    for (var targSel in data.data) {
        var val = data.data[targSel];
        //mylog(`writing to '${targSel}' value '${val}'`);
        $( targSel ).each(function() {
            var $targ = $( this );
            const id = $targ.attr('id');

            // skip element if it's in active editable mode:
            if ($targ.hasClass('lzy-editable-active')) {
                mylog('live-data: skipping active editable field: ' + id);
                return;
            }

            var goOn = true;
// var callback = $('.lzy-live-data').attr('data-live-callback');
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

            var postCallback = $targ.attr('data-live-post-update-callback');
            if (typeof window[postCallback] === 'function') {
                window[postCallback]( targSel, val );
            }
        });
        if (debugOutput) {
            console.log(targSel + ' -> ' + val);
        }
    }
// var postCallback = $('.lzy-live-data').attr('data-live-post-update-callback');
// if (typeof window[postCallback] === 'function') {
//     window[postCallback]( data.data );
// }
} // updateDOM




function handleAjaxResponse(json) {
    ajaxHndl = null;
    console.log('ajax: ' + json);
    if (!json) {
        console.log('No data received - terminating live-data');
        return;
    }
    json = json.replace(/(.*[\]}])\#.*/, '$1');    // remove trailing #comment
    try {
        var data = JSON.parse(json);
    } catch (e) {
        console.log('Error condition detected - terminating live-data');
        console.log(json);
        return false;
    }

    if (typeof data.lastUpdated !== 'undefined') {
        lastUpdated = data.lastUpdated;
    }
    if (typeof data.result === 'undefined') {
        console.log('_live_data_service.php reported an error');
        console.log(json);
        return;
    }

    // regular response:
    if (typeof data.data === 'undefined') {
        if (debugOutput) {
            console.log(timeStamp() + ': No new data');
        }

    } else {
        var goOn = true;
        if (typeof liveDataCallback === 'function') {
            goOn = liveDataCallback( data.data );
        }
        if (goOn) {
            updateDOM(data);
        }
        if (typeof liveDataPostUpdateCallback === 'function') {
            goOn = liveDataPostUpdateCallback( data.data );
        }
    }
    $('.live-data-update-time').text(timeStamp());
    updateLiveData();
} // handleAjaxResponse




function updateLiveData( returnImmediately ) {
    var url = appRoot + "_lizzy/extensions/livedata/backend/_live_data_service.php";
    if (paramName) {
        var paramValue = $( paramSource ).text();
        url = appendToUrl(url, 'dynDataSel=' + paramName + ':' + paramValue);
        if (paramValue !== prevParamValue) {
            returnImmediately = true;
            prevParamValue = paramValue;
        }
    }
    if (typeof returnImmediately !== 'undefined') {
        url = appendToUrl(url, 'returnImmediately');
    }

    if (ajaxHndl !== null){
        ajaxHndl.abort();
    }
    ajaxHndl = $.ajax({
        url: url,
        type: 'POST',
        data: { ref: refs, last: lastUpdated },
        async: true,
        cache: false,
    }).done(function ( json ) {
        return handleAjaxResponse(json);
    });
} // update




$(window).bind('beforeunload', function(e) {
    abortAjax();
});



function abortAjax()
{
    if (ajaxHndl !== null){
        ajaxHndl.abort();
        ajaxHndl = null;
        console.log('live-data: last ajax call aborted');
    }
    $.ajax({
        url: appRoot + "_lizzy/_ajax_server.php?abort=true",
        type: 'GET',
        cache: false,
    }).done(function () {
        console.log('live-data: server process aborted');
    });
}