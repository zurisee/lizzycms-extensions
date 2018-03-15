/*
 *  Editable.js
*/

var editableObj = new Object();
var keyTimeout = 0;
var semaphoreWait = false;
editableObj.pendingReq = false;
editableObj.doSave = true;

(function ( $ ) {

    $.fn.editable = function( options ) {
        var settings = $.extend({    // default values:
            'backend': systemPath+'_ajax_server.php',
            'showButton': 'auto', // 'auto', true, false
        }, options );

        if ( this.length == 0 ) {
            return;
        }
        var invokingClass = $(this).attr('class').replace(/\s.*/, '');
        if (typeof invokingClass == 'undefined') {
            invokingClass = 'lzy-editable';
        }
        initializeEditableField(invokingClass, settings);
    };
    
    $( window ).on("unload", function() {
        console.log( ".unload() called." );
        if (editableObj.pendingReq) {
            editableObj.pendingReq.abort();
        }
        $.get( systemPath+'_ajax_server.php?end', function( data ) {
            console.log(data);
        });
    });


//    $('.$editable').editable(); //??? -> needed, in case of invokation without macro: unclear how to handle best
}( jQuery ));

function initializeEditableField(invokingClass, settings)
{
    var edObj = editableObj;
    edObj.editModeTimeout = 10000;  // automatically terminate editing after 20s of no key activity
    edObj.longPollingTime = 60;
    edObj.shortPollingTime = 10;
    edObj.currentRequest = null;
    edObj.invokingClass = invokingClass;
    edObj.isTouchDevice = 'ontouchstart' in document.documentElement;
    edObj.backend = settings.backend; // appRoot supplied by script snippet in header
    if (settings.showButton == 'auto') {
        edObj.showButton = edObj.isTouchDevice;
    } else {
        edObj.showButton = (settings.showButton == 'true');
    }
    edObj.loadOnly = settings.loadOnly;
    edObj.lastText = '';
    edObj.inpWidth = {};
    edObj.btnWidth = {};
    edObj.editing = false;
    edObj.keyTimeout = false;
    edObj.fieldIDs = '';

    initEditableFields();
    setupKeyHandler();
    if (!initializeConnection()) {
        return;
    }

    $(window).resize(function() {
        setWidths(edObj);
    });

    $(window).bind('beforeunload', function(){
        remoteLog('Client disconnected');
        endEdit(edObj, false, true);
    });
    
    myLog('Editable.js started for class ".'+edObj.invokingClass+'"'+ ((edObj.showButton) ? ' in touch screen' : ' in desktop') + ' mode (backend: '+ edObj.backend+')');
    if (edObj.showButton) {
        myLog('showButton enabled!');
    }
} // initializeEditableField



//--------------------------------------------------------------
function initEditableFields()
{
    var edObj = editableObj;
    var $editable = $('.'+edObj.invokingClass);
    var h = $editable.height();

    if ($('body').hasClass('legacy')) {
        var okSymbol = '&radic;';
    } else {
        var okSymbol = '&#10003;';
    }
    
    $editable.each(function(inx, elem) {
        var $elem = $(elem);
        var id = $elem.attr('id');
        edObj.showButton = edObj.showButton || $elem.hasClass('lzy-editable-show-button');

        if (typeof id == 'undefined') {
            id =  edObj.invokingClass + '_field' + (parseInt(inx) + 1);
            $elem.attr('id', id);
        }

        edObj.fieldIDs = edObj.fieldIDs + id + ','; // keep track of fieldIDs

        var origText = $elem.text();
        // if (!edObj.loadOnly) {
            $elem.text('');
            var _id = '_'+id;
            var label = $elem.attr('title');
            var buttons = '';
            if (edObj.showButton) {
                buttons = "<div class='lzy-edit_buttons'><button class='lzy-button submit'>"+okSymbol+"<span class='invisible'> Save</span></button>";
            }
            if (typeof label == 'undefined') {
                $elem.html('<input id="'+_id+'" aria-live="polite" aria-relevant="all" value="x" />'+buttons);
            } else {
                $elem.html('<label for="'+_id+'" class="invisible">'+label+'</label><input id="'+_id+'" aria-live="polite" aria-relevant="all" value="y" />'+buttons);
            }
            $('#'+_id).val(origText);
            setWidth(edObj, id);
        // }
    }); // .each


    $('.lzy-edit_buttons button').height(h - 4);
    var $edInput = $('input', $editable);
    
    // activate 'loading' indication
    $editable.addClass('lzy-wait');

    $edInput.focus(function(e) {
        var id = $(this).parent().attr('id');
        if (!$('#_'+id).hasClass('lzy-locked')) {
            initEdit(edObj, id);
        } else {
            myLog('focus: input field is locked ('+id+')');
            e.preventDefault();
        }
    });

    $edInput.blur(function(e) {
        var _id = $(this).attr('id');
        setTimeout(function() {
            endEdit(edObj, _id, edObj.doSave);
        }, 100);
    });

    $('.lzy-edit_buttons button.submit').click(function(e) {
        edObj.doSave = true;
        // var id = $(this).parent().parent().attr('id');
        // $('#_'+id).blur();
    });
} // initEditableFields()



//--------------------------------------------------------------
function setWidths(edObj)
{
    var $editable = $('.'+this.invokingClass);
    $editable.each(function(inx, elem) {
        var id = $(elem).attr('id');
        setWidth(edObj, id);
    });
} // setWidths



function setWidth(edObj, id)
{
    var _id = '_'+id;
    var $elem = $('#'+id);
    var $inpElem = $('#'+_id);
    var height = $elem.height();
    var width = $elem.width() - parseInt($inpElem.css('padding-right')) - parseInt($inpElem.css('padding-left')) - 2;
    var btnWidth = height * 1.5;
    $inpElem.width(width).height(height-2);
    $('button', $elem).width(btnWidth).height(height);
    edObj.inpWidth[_id] = $elem.width();
    edObj.btnWidth[_id] =  (btnWidth + 1);
} // setWidth



//--------------------------------------------------------------
function setupKeyHandler()
{
    var edObj = editableObj;
    $('.'+edObj.invokingClass+' input').keypress(function (e) {
        if ($(e.target).parent().hasClass('lzy-wait')) {
            e.preventDefault();

        } else {                // reset editing mode timeout
            if (edObj.keyTimeout) {
                clearTimeout(edObj.keyTimeout);
            }
            var _id = $(e.target).attr('id');
            edObj.keyTimeout = setTimeout(timeoutAction, edObj.editModeTimeout);
        }
    });
    $('.'+edObj.invokingClass+' input').keyup(function (e) {
        var key = e.which;
        var _id = $(e.target).attr('id');
        if (key == 13) {             // Enter
            myLog('keypress: Enter');
            e.preventDefault();
            edObj.doSave = true;
            $('#'+_id).blur();
            return false;
        }
        if(key == 27) {                // ESC
            myLog('keyup: Esc ('+_id+') ['+edObj.lastText+']');
            $('#'+_id).val(edObj.lastText);
            $('#'+_id).blur();
            return false;
        }
    });
} // setupKeyHandler




function timeoutAction()
{
    console.log('edit mode timed out');
    $('.lzy-editable_active').blur();
}



//--------------------------------------------------------------
function initEdit(edObj, id)
{
    console.log('initEdit: '+id);
    edObj.doSave = !edObj.showButton;

    // try to lock, repeat if failed:
    if (!this.lock(id, edObj)) {
        // handle situation where user clicks from one field immediately into the next
        $('#' + id + ' input').prop('disabled', true);
        console.log('initEdit -> sem locked, waiting on ' + id);

        var cnt = 0;
        var intv = setInterval(function () {
            if (cnt >= 5) {         // give up:
                clearInterval(intv);
                console.log('initEdit -> sem still locked, giving up on ' + id);
                $('#' + id + ' input').prop('disabled', false);
                endEdit(edObj, '_' + id, false);
                return;
            }
            cnt++;                  // try again:
            console.log('initEdit -> trying again ('+cnt+') on ' + id);
            if (this.lock(id, edObj)) {
                $('#' + id + ' input').prop('disabled', false);
                clearInterval(intv);
                initEditCont(id, edObj);
                return;
            }
        }, 1000);
    } else {
        initEditCont(id, edObj);
    }
} // initEdit




function initEditCont(id, edObj)
{
    edObj.keyTimeout = setTimeout(timeoutAction, edObj.editModeTimeout);

    if (edObj.showButton) {
        var _id = '_'+id;
        var shrunkWidth = edObj.inpWidth[_id] - edObj.btnWidth[_id] - 4;
        $('#'+_id).animate({'width': shrunkWidth}, 250);
    }
}




//--------------------------------------------------------------
function endEdit(edObj, _id, sendToServer)
{
    if (edObj.keyTimeout) {
        clearTimeout(edObj.keyTimeout);
        edObj.keyTimeout = false;
    }
    console.log('endEdit: '+_id);
    if (!_id) {
        _id = $('.lzy-editable_active').attr('id');
    }
    if (typeof _id == 'undefined') {
        myLog('Error: endEdit -> no lzy-editable_active found');
        return;
    }
    var $edInput = $('#'+_id);
    if (edObj.showButton) {
        $edInput.animate({'width': edObj.inpWidth[_id]}, 250);
    }
    $edInput.removeClass('lzy-editable_active');
    var val = $edInput.val();
    if (typeof val != 'undefined') {
        if (sendToServer && (val != edObj.lastText)) {
            ajaxSend(edObj, 'save', _id.substr(1), val, false);
        } else {
            unlock(_id.substr(1), edObj);
            $('#'+_id).val(edObj.lastText);
        }
        edObj.lastText = '';
        edObj.editing = false;
    } else {
        myLog('Error: endEdit -> no value available');
    }
} // endEdit



//--------------------------------------------------------------
function lock(id, edObj)
{
    if (semaphoreWait) {
        return false;
    }
    myLog('locking #'+id);
    this.ajaxSend(edObj, 'lock', id, '', function(json) {
        if (json == 'ok') {
            var _id = '_' + id;
            edObj.editing = id;
            var $edInput = $('#' + _id);
            edObj.lastText = $edInput.val();
            $edInput.addClass('lzy-editable_active').select();
        } else {
            myLog('% lock failed');
            endEdit(edObj, id, false);
            // $('#' + id).blur();
        }
    });
    return true;
} // lock



//--------------------------------------------------------------
function unlock(id, edObj)
{
    myLog('unlocking #'+id);
    this.ajaxSend(edObj, 'unlock', id, '', false);
} // unlock



//--------------------------------------------------------------
function initializeConnection()
{
    var edObj = editableObj;
    var res = false;
    this.ajaxSend(edObj, 'conn', '', edObj.fieldIDs, function(json) {
        myLog('Server connection established: ' + json);
        if (json.match(/failed/)) {
            myLog('Session aborted');
            $('main').html("<h1>Error connecting to server.</h1><p>Please try again with another browser or another computer.</p>");
            res = false;
        }
        res = true;
    });
    return res;
} // initializeConnection



//--------------------------------------------------------------
function update(pollingTime)
{
    var edObj = editableObj;
    if (typeof pollingTime == 'undefined') {
        pollingTime = (edObj.isTouchDevice) ? edObj.shortPollingTime : edObj.longPollingTime;
    }
    this.ajaxSend(edObj, 'upd', '', pollingTime, function(json) {
        myLog('sendAjax upd response received: '+json);
    });
} // update




//--------------------------------------------------------------
function ajaxSend(edObj, cmd, id, data, onSuccess, synchronous)
{
    if (cmd == 'upd') {
        if (edObj.pendingReq) {
            myLog('ajax-upd skipped: '+edObj.pendingReq);
            return;
        }
        edObj.pendingReq = $.ajax( edObj.backend+"?upd="+data, {
            cache: false,
            success: function (json) {
                edObj.pendingReq = false;
                console.log('ajax-upd: '+json);
                updateUi(edObj, json);
                update();
            }
        });

    } else {    // all interactions but upd:
        semaphoreWait = true;
        if (id) {
            $('#' + id).addClass('lzy-wait');
        }
        if (edObj.pendingReq) {
            mylog('upd was pending, aborting now');
            edObj.pendingReq.abort();
            edObj.pendingReq = false;
        }
        if (cmd == 'conn') {
            var method = 'GET';
            var arg = 'conn='+data;
            var postData = '';

        } else if (cmd == 'save') {
            data = encodeURIComponent(data);
            var method = 'POST';
            var arg = 'save='+id;
            var postData = 'text='+data;

        } else {
            var method = 'GET';
            var arg = cmd+'='+id;
            var postData = '';
        }

        $.ajax({
            url: edObj.backend + "?" + arg,
            type: method,
            data: postData,
            cache: false,
            success: function (json) {
                semaphoreWait = false;
                if ((json != 'failed') && onSuccess) {
                    onSuccess(json);
                }
                updateUi(edObj, json);
                update(1);
            }
        });
    }
} // ajaxSend


//--------------------------------------------------------------
function updateUi(edObj, json)
{
    if (json && json.match(/^\{/)) {
        myLog('updateUi: ['+json+']');
        json = json.replace(/(\{.*\}).*/, "$1");    // remove trailing #comment
        var data = JSON.parse(json);
        if (data) {
            var editingField = $('.lzy-editable_active').attr('id');
            for (var id in data) {
                if ((id == 'undefined') || !id || (id.substr(0,1) == '_')) {
                    continue;
                }

                if (editingField == '_'+id) {  // curr editing field is editing field
                    continue;   // do not update editing field
                }

                var $input = $('#'+id+' input');
                if (typeof data[id] != 'undefined') {
                    var value = data[id];
                    if (value.match(/\*\*LOCKED\*\*/)) {
                        value = data[id].replace('**LOCKED**', '');
                        $input.addClass('lzy-locked').attr('title', 'Currently being editied by another user.').attr('disabled','');

                    } else if ($input.hasClass('lzy-locked')) {
                        $input.removeClass('lzy-locked').removeAttr('title').removeAttr('disabled');
                        $('.lzy-edit_buttons', '#'+id).show();
                    }
                    $input.val(value);
                }
            }
        }
    }
    
    // de-activate 'loading' indication
    $('.lzy-wait').removeClass('lzy-wait');

} // updateUi



//--------------------------------------------------------------
function myLog( text )
{
    if (typeof mylog !== "undefined") {
        mylog(text);
    } else {
        console.log(text);
    }
} // myLog



//--------------------------------------------------------------
function remoteLog(text)
{
    if (!text) {
        return;
    }
    var edObj = editableObj;
    $.ajax({
        url: edObj.backend+'?log='+text,
        type: 'GET',
        data: '',
        async: true,
        cache: false,
    }).fail(function() {
        myLog('Error: no backend found');
    });
} // remoteLog()

